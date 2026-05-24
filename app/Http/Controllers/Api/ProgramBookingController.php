<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\BookingDateAndTime;
use App\Models\Commission;
use App\Models\ErProgram;
use App\Models\ErProgramTime;
use App\Models\ProgramBooking;
use App\Models\Setting;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Stripe\Checkout\Session;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;
use Throwable;

class ProgramBookingController extends Controller
{
    use ApiResponse;

    private function getActiveCommissionForRole(?string $role, ?int $ownerUserId = null): ?Commission
    {
        $normalizedRole = strtolower((string) $role);
        if (! in_array($normalizedRole, ['coach', 'club'], true)) {
            $normalizedRole = 'all';
        }

        return Commission::query()
            ->where('status', 'active')
            ->where(function ($query) use ($normalizedRole, $ownerUserId): void {
                if ($ownerUserId) {
                    $query->where('user_id', $ownerUserId);
                }

                $query->orWhere(function ($nested) use ($normalizedRole): void {
                    $nested->whereNull('user_id')
                        ->whereIn('applies_to', [$normalizedRole, 'all']);
                });
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 WHEN applies_to = ? THEN 1 ELSE 2 END', [$ownerUserId ?: 0, $normalizedRole])
            ->latest('id')
            ->first();
    }

    private function calculateBookingAmounts(float $mainAmount, float $discountAmount, float $taxPercent): array
    {
        $mainAmount = round(max(0, $mainAmount), 2);
        $discountAmount = round(max(0, min($discountAmount, $mainAmount)), 2);
        $subtotal = round($mainAmount - $discountAmount, 2);
        $taxPercent = round(max(0, $taxPercent), 2);
        $taxAmount = round(($subtotal * $taxPercent) / 100, 2);
        $payableAmount = round($subtotal + $taxAmount, 2);

        return [
            'amount' => $mainAmount,
            'discount' => $discountAmount,
            'tax' => $taxPercent,
            'tax_amount' => $taxAmount,
            'payable_amount' => $payableAmount,
        ];
    }

    private function calculateCommissionAmount(float $grossAmount, ?string $role, ?int $ownerUserId = null): float
    {
        $commission = $this->getActiveCommissionForRole($role, $ownerUserId);

        if (! $commission) {
            return 0.0;
        }

        $type = strtolower((string) $commission->type);
        $amount = (float) $commission->amount;
        $commissionAmount = $type === 'percentage'
            ? ($grossAmount * $amount) / 100
            : $amount;

        return round(max(0, min($grossAmount, $commissionAmount)), 2);
    }

    private function calculatePayableFromBooking(ProgramBooking $booking): float
    {
        $amount = (float) ($booking->amount ?? 0);
        $discount = (float) ($booking->discount ?? 0);
        $taxPercent = (float) ($booking->tax ?? 0);
        $subtotal = max(0, $amount - $discount);
        $taxAmount = ($subtotal * $taxPercent) / 100;

        return round($subtotal + $taxAmount, 2);
    }

    private function mapBookingTimeSlot(?ErProgramTime $time): ?array
    {
        if (! $time) {
            return null;
        }

        return [
            'id' => $time->id,
            'time' => $time->time,
            'slot_date' => optional($time->slot_date)?->toDateString(),
            'start_time' => $time->start_time,
            'end_time' => $time->end_time,
            'is_available' => (bool) ($time->is_available ?? true),
        ];
    }

    private function isProgramTimeBooked(ErProgram $program, ErProgramTime $time, string $date): bool
    {
        // For 'one_one' programs, any active booking (pending or paid) blocks the slot.
        // For 'group' programs, more than one person can book.
        $bookingType = (string) ($program->program_type ?: 'one_one');

        if ($bookingType === 'one_one') {
            return ProgramBooking::query()
                ->where('program_id', $program->id)
                ->where('booking_date', $date)
                ->where('booking_time_id', $time->id)
                ->whereIn('status', ['pending', 'confirmed', 'completed'])
                ->whereIn('payment_status', ['pending', 'paid'])
                ->exists();
        }

        return false;
    }

    private function buildTimeSlotStatus(ErProgram $program, ErProgramTime $time, string $date): array
    {
        $isBooked = $this->isProgramTimeBooked($program, $time, $date);
        $isPastDate = Carbon::parse($date)->startOfDay()->lt(Carbon::now()->startOfDay());

        $isAvailable = (bool) ($time->is_available ?? true) && ! $isPastDate;

        // For one_one, if booked, then not available
        if ((string)($program->program_type ?: 'one_one') === 'one_one') {
            if ($isBooked) {
                $isAvailable = false;
            }
        }

        return [
            'id' => $time->id,
            'time' => $time->time,
            'slot_date' => optional($time->slot_date)?->toDateString() ?: $date,
            'start_time' => $time->start_time,
            'end_time' => $time->end_time,
            'is_available' => $isAvailable,
            'is_booked' => $isBooked,
            'is_past' => $isPastDate,
        ];
    }

    private function buildDateAvailability(ErProgram $program, string $date, $times): array
    {
        $times = collect($times);
        $isPastDate = Carbon::parse($date)->startOfDay()->lt(Carbon::now()->startOfDay());
        $hasSlots = $times->isNotEmpty();
        $hasAvailableSlots = $times->contains(function ($time) use ($program, $date): bool {
            $isAvailable = (bool) data_get($time, 'is_available', true);
            $isBooked = (bool) data_get($time, 'is_booked', false);

            if ($time instanceof ErProgramTime) {
                $isBooked = $this->isProgramTimeBooked($program, $time, $date);
                $isAvailable = (bool) ($time->is_available ?? true);
            }

            return $isAvailable
                && ! $isBooked
                && Carbon::parse($date)->startOfDay()->gte(Carbon::now()->startOfDay());
        });

        return [
            'date' => $date,
            'has_slots' => $hasSlots,
            'has_available_slots' => $hasAvailableSlots,
            'is_available' => $hasSlots && $hasAvailableSlots && ! $isPastDate,
            'is_past' => $isPastDate,
        ];
    }

    public function bookProgram(Request $request)
    {
        $user = Auth::guard('api')->user();
        // dd($user);

        try {
            if (! $user) {
                return response()->json(['status' => false, 'message' => 'Authentication required.'], 401);
            }

            $validator = Validator::make($request->all(), [
                'program_id' => 'required|integer|exists:er_programs,id',
                'athlete_profile_id' => 'required|integer|exists:athlete_profiles,id',
                'booking_time_id' => 'required', // Can be an integer or an array of integers
                'booking_date' => 'nullable|date',
                // 'amount' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'country_code' => 'nullable|string|size:2',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $program = ErProgram::query()->with(['coach:id,user_id', 'user:id,role'])->find($request->program_id);
            if (! $program) {
                return response()->json(['status' => false, 'message' => 'Program not found.'], 404);
            }

            $athlete = AthleteProfiles::query()->find($request->athlete_profile_id);
            if (! $athlete) {
                return response()->json(['status' => false, 'message' => 'Athlete profile not found.'], 404);
            }

            $parentId = $athlete->parent_id;
            if (! $parentId && in_array($user->role, ['parent', 'player'], true)) {
                $parentId = $user->id;
            }

            if ($user->role === 'parent' && $athlete->parent_id && (int) $athlete->parent_id !== (int) $user->id) {
                return response()->json(['status' => false, 'message' => 'This athlete is not linked with your parent account.'], 403);
            }

            if (! $parentId) {
                return response()->json(['status' => false, 'message' => 'Parent account is required for booking.'], 422);
            }

            $programOwnerUserId = (int) ($program->user_id ?: $program->coach?->user_id ?: 0);
            if ($programOwnerUserId <= 0) {
                return response()->json(['status' => false, 'message' => 'Program owner account not linked with this program.'], 422);
            }

            $bookingTimeIds = is_array($request->booking_time_id) ? $request->booking_time_id : [$request->booking_time_id];
            // Ensure all IDs are integers
            $bookingTimeIds = array_map('intval', $bookingTimeIds);

            $bookingTimes = ErProgramTime::query()
                ->whereIn('id', $bookingTimeIds)
                ->where('er_program_id', $request->program_id)
                ->get();

            if ($bookingTimes->isEmpty() || count($bookingTimes) !== count($bookingTimeIds)) {
                return response()->json([
                    'status' => false,
                    'message' => 'One or more selected booking times do not belong to this program.',
                ], 422);
            }

            $totalSlots = count($bookingTimes);
            $bookingTime = $bookingTimes->first();

            $bookingType = (string) ($program->program_type ?: 'one_one');
            $bookingDate = $request->input('booking_date');
            if (! $bookingDate && $bookingTime->slot_date) {
                $bookingDate = $bookingTime->slot_date->toDateString();
            }
            if (! $bookingDate) {
                $bookingDate = Carbon::now()->toDateString();
            }

            if ($bookingType === 'one_one') {
                $activeSlotBookingExists = ProgramBooking::query()
                    ->where('program_id', $program->id)
                    ->where('booking_date', $bookingDate)
                    ->whereIn('status', ['pending', 'confirmed', 'completed'])
                    ->whereIn('payment_status', ['pending', 'paid'])
                    ->whereIn('booking_time_id', $bookingTimeIds)
                    ->exists();

                if ($activeSlotBookingExists) {
                    return response()->json([
                        'status' => false,
                        'message' => 'One or more selected slots are not available.',
                    ], 422);
                }
            }

            $basePrice = (float) $program->program_price;
            $mainAmount = $basePrice * $totalSlots;

            $discountAmount = 0.0;
            // Calculate total discount based on per-slot discount amount in DB
            if ((float)$program->program_price > 0 && (float)$program->discount_price > 0) {
                // Here we assume discount_price IS the amount to be subtracted per slot
                // Example: Price 100, Discount 20, Slots 2 => Total Main 200, Total Discount 40
                $discountAmount = (float)$program->discount_price * $totalSlots;
            }

            $countryCode = strtoupper((string) ($request->input('country_code') ?: 'CA'));
            // $taxPercent = $countryCode === 'CA' ? 13.0 : 0.0;
            $taxPercent = $request->input('tax_percent') !== null ? (float) $request->input('tax_percent') : ($countryCode === 'CA' ? 13.0 : 0.0);
            $amounts = $this->calculateBookingAmounts($mainAmount, $discountAmount, $taxPercent);
            $payableAmount = (float) $amounts['payable_amount'];
            //   dd($payableAmount);
            $currency = strtolower((string) ($request->input('currency') ?: ($countryCode === 'CA' ? 'cad' : 'usd')));

            if ($payableAmount <= 0) {
                return response()->json(['status' => false, 'message' => 'Invalid amount.'], 422);
            }

            $stripeSecret = $this->resolveStripeSecret();
            if (! $stripeSecret) {
                return response()->json(['status' => false, 'message' => 'Stripe secret is not configured.'], 500);
            }

            Stripe::setApiKey($stripeSecret);

            $programOwnerRole = strtolower((string) ($program->user?->role ?: ($program->coach_id ? 'coach' : 'all')));
            $commissionAmount = $this->calculateCommissionAmount($payableAmount, $programOwnerRole, $programOwnerUserId);
            $afterCommissionAmount = round($payableAmount - $commissionAmount, 2);

            $booking = ProgramBooking::query()->create([
                'program_id' => $program->id,
                'athlete_profile_id' => $athlete->id,
                'parent_id' => $parentId,
                'coach_id' => $programOwnerUserId,
                'booking_time_id' => is_array($request->booking_time_id) ? $request->booking_time_id[0] : (int) $request->booking_time_id,
                'booking_date' => $bookingDate,
                'amount' => $amounts['amount'],
                'discount' => $amounts['discount'],
                'tax' => $amounts['tax'],
                'after_commission_amount' => (float) round($afterCommissionAmount, 2),
                'commission_amount' => (float) round($commissionAmount, 2),
                'currency' => $currency,
                'payment_status' => 'pending',
                'status' => 'pending',
                'multiple_slots' => is_array($request->booking_time_id) ? json_encode($request->booking_time_id) : null,
            ]);

            $successUrl = $request->input('success_url', route('stripe.success'));
            $cancelUrl = $request->input('cancel_url', route('stripe.cancel'));

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Program Booking: ' . (string) $program->program_name,
                        ],
                        'unit_amount' => (int) round($payableAmount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);

            $booking->stripe_session_id = $session->id;
            $booking->save();

            return response()->json([
                'status' => true,
                'message' => 'Checkout session created successfully.',
                'data' => [
                    'booking_id' => $booking->id,
                    'program_type' => $bookingType,
                    'booking_date' => optional($booking->booking_date)?->toDateString(),
                    'booking_time' => $this->mapBookingTimeSlot($bookingTime),
                    'checkout_url' => $session->url,
                    'financials' => [
                        'amount' => $amounts['amount'],
                        'discount' => $amounts['discount'],
                        'tax' => $amounts['tax'],
                        'tax_amount' => $amounts['tax_amount'],
                        'payable_amount' => $payableAmount,
                        'currency' => strtoupper($currency),
                    ],
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Booking failed: ' . $e->getMessage(),
            ], 500);
        }
    }







    public function clubProgramBookings(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || $user->role !=='club') {
                return response()->json([
                    'status' => false,
                    'message' => 'Only clubs can view their program bookings.'
                ], 403);
            }

            $query = ProgramBooking::with([
                'program',
                'athlete:id,name,last_name,image,user_id',
                'athlete.user:id,profile_image',
                'coach:id,name,last_name,profile_image',
                'bookingTime',
                'parent:id,name,last_name,profile_image'
            ])
                ->where('coach_id', $user->id) // Club user is the owner
                ->latest();

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if($request->program_id){
                $query->where('program_id', $request->program_id);
            }

            $bookings = $query->get();


         $programPlayerndcoachCount = $bookings->where('program_id', $request->program_id)->count();


            $formattedBookings = $bookings->map(function ($booking) {
                $bookingData = $booking->toArray();
                if ($booking->program && $booking->program->program_photo) {
                    $bookingData['program']['program_photo'] = asset($booking->program->program_photo);
                }

                // Map provider (the person who booked)
                $athlete = [
                    'name' => '',
                    'profile_image' => null
                ];

                if ($booking->athlete) {
                    $athlete['name'] = $booking->athlete->name . ' ' . $booking->athlete->last_name;
                    // অ্যাথলেট প্রোফাইলের ইমেজ অথবা ইউজার প্রোফাইল ইমেজ চেক করা হচ্ছে
                    $imagePath = $booking->athlete->image ?: ($booking->athlete->user ? $booking->athlete->user->profile_image : null);
                    $athlete['profile_image'] = $imagePath ? asset($imagePath) : null;
                } elseif ($booking->parent) {
                    // অ্যাথলেট না থাকলে প্যারেন্টের ব্যাকআপ তথ্য
                    $athlete['name'] = $booking->parent->name . ' ' . $booking->parent->last_name;
                    $athlete['profile_image'] = $booking->parent->profile_image ? asset($booking->parent->profile_image) : null;
                }

                $bookingData['athlete'] = $athlete;

                return $bookingData;
            });

            return response()->json([
                'status' => true,
                'message' => 'Club program bookings retrieved successfully.',
                'data' => $formattedBookings,
                'program_player_count' => $programPlayerndcoachCount??0
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve bookings: ' . $e->getMessage()
            ], 500);
        }
    }

















    public function availableSlots(Request $request, $program_id)
    {
        try {
            $program = ErProgram::query()->find($program_id);
            if (! $program) {
                return $this->notFound([], 'Program not found', 404);
            }

            $month = $request->query('month', Carbon::now()->format('Y-m'));
            try {
                $start = Carbon::parse($month . '-01')->startOfMonth();
                $end = Carbon::parse($month . '-01')->endOfMonth();
            } catch (\Throwable $e) {
                return $this->validationError(['month' => ['Invalid month format']], 'Validation failed', 422);
            }

            $times = ErProgramTime::query()
                ->where('er_program_id', $program->id)
                ->where('slot_date', '>=', $start->toDateString())
                ->where('slot_date', '<=', $end->toDateString())
                ->get();

            $grouped = [];
            foreach ($times as $t) {
                $date = optional($t->slot_date)?->toDateString();
                if (! $date) {
                    continue;
                }

                if (! isset($grouped[$date])) {
                    $grouped[$date] = ['date' => $date, 'times' => []];
                }

                $grouped[$date]['times'][] = $this->buildTimeSlotStatus($program, $t, $date);
            }

            // ensure all days in month are present
            $days = [];
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $d = $cursor->toDateString();
                $days[] = isset($grouped[$d])
                    ? array_merge($grouped[$d], $this->buildDateAvailability($program, $d, collect($grouped[$d]['times'])))
                    : [
                        'date' => $d,
                        'has_slots' => false,
                        'has_available_slots' => false,
                        'is_available' => false,
                        'is_past' => Carbon::parse($d)->startOfDay()->lt(Carbon::now()->startOfDay()),
                        'times' => [],
                    ];
                $cursor->addDay();
            }

            return $this->success([
                'month' => $start->format('Y-m'),
                'days' => $days,
            ], 'Available slots fetched', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function availableTimes(Request $request, $program_id)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        try {
            $program = ErProgram::query()->find($program_id);
            if (! $program) {
                return $this->notFound([], 'Program not found', 404);
            }

            $date = Carbon::parse($request->input('date'))->toDateString();

            $times = ErProgramTime::query()
                ->where('er_program_id', $program->id)
                ->where('slot_date', $date)
                ->get();

            $result = $times->map(function ($t) use ($program, $date) {
                return $this->buildTimeSlotStatus($program, $t, $date);
            })->values();

            return $this->success([
                'date' => $this->buildDateAvailability($program, $date, $times),
                'times' => $result,
            ], 'Times fetched', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');
        $stripeSettings = Setting::getGroup('stripe');
        $secret = $stripeSettings['webhook_secret'] ?? getenv('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent($payload, $sig, $secret);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid webhook'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $paymentIntentId = $session->payment_intent;

            $booking = ProgramBooking::query()->with(['program', 'bookingTime', 'bookingDateTime'])->where('stripe_session_id', $session->id)->first();

            if ($booking) {
                $booking->stripe_payment_intent_id = $paymentIntentId;
                $booking->payment_status = 'paid';
                $booking->status = 'confirmed';
                $booking->save();

                // Generate booking date and time record if it doesn't already exist
                if ($booking->bookingDateTime->isEmpty()) {
                    $bookingType = (string) ($booking->program?->program_type ?: 'one_one');
                    $timeIds = $booking->multiple_slots ? json_decode($booking->multiple_slots, true) : [$booking->booking_time_id];

                    foreach ($timeIds as $timeId) {
                        $t = ErProgramTime::find($timeId);
                        if ($t) {
                            BookingDateAndTime::query()->create([
                                'program_booking_id' => $booking->id,
                                'booking_time_id' => $t->id,
                                'booking_date' => $booking->booking_date,
                                'booking_type' => in_array($bookingType, ['one_one', 'group'], true) ? $bookingType : 'one_one',
                                'time_label' => $t->time,
                                'start_time' => $t->start_time,
                                'end_time' => $t->end_time,
                            ]);
                        }
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }


    public function updateStatus(Request $request, $id)
    {
        try {

            $user = Auth::guard('api')->user();
            if (! $user) {
                return response()->json(['status' => false, 'message' => 'Authentication required.'], 401);
            }

            $booking = ProgramBooking::query()->with(['program', 'bookingTime', 'bookingDateTime'])->find($id);
            if (! $booking) {
                return response()->json(['status' => false, 'message' => 'Booking not found.'], 404);
            }

            if ($request->status === 'cancelled' && $booking->payment_status === 'paid') {
                $stripeSecret = $this->resolveStripeSecret();
                if (! $stripeSecret) {
                    return response()->json(['status' => false, 'message' => 'Stripe secret is not configured.'], 500);
                }

                Stripe::setApiKey($stripeSecret);
                $booking->payment_status = 'refunded';
                Refund::create([
                    'payment_intent' => $booking->stripe_payment_intent_id,
                    'amount' => (int) round(((float) $booking->amount) * 100),
                ]);
            }

            $booking->status = $request->status;

            $booking->save();

            return response()->json(['status' => true, 'message' => 'Booking status updated successfully.'], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to cancel booking: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function resolveStripeSecret(): ?string
    {
        $stripeSettings = Setting::getGroup('stripe');

        return $stripeSettings['stripe_secret_key']
            ?? $stripeSettings['secret']
            ?? getenv('STRIPE_SECRET')
            ?: null;
    }

    public function AthleteParentlistBookings(Request $request)
    {

        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return response()->json(['status' => false, 'message' => 'Authentication required.'], 401);
            }

            $activeChildId = $request->header('active-child-id') ?? $request->get('active_child_id');

            if (! in_array($user->role, ['parent', 'player'], true)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only parent or player can view bookings.',
                ], 403);
            }

            $baseQuery = ProgramBooking::query()
                ->with([
                    'program',
                    'coach:id,name,last_name,profile_image',
                    'coach.coachProfile:id,user_id,coach_profile_pic',
                    'coach.club:id,user_id,club_logo',
                    'bookingTime'
                ])
                ->whereHas('program', function ($query) {
                    $query->whereDate('program_end', '>=', now()->toDateString());
                });

            if ($activeChildId) {
                // If parent is viewing a specific child's dashboard
                $baseQuery->where('athlete_profile_id', $activeChildId);
            } elseif ($user->role === 'parent') {
                $baseQuery->where('parent_id', $user->id);
            } else {
                $athleteProfileId = AthleteProfiles::query()->where('user_id', $user->id)->value('id');

                if (! $athleteProfileId) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Athlete profile not found for this player.',
                    ], 404);
                }

                $baseQuery->where('athlete_profile_id', $athleteProfileId);
            }

            $activeBookingslast = (clone $baseQuery)
                ->latest()
                ->first();

            $bookings = (clone $baseQuery)
                ->latest()
                ->get();

            $formattedBookings = $bookings->map(function ($booking) {
                $item = $booking->toArray();
                if (! empty($item['program']['program_photo'])) {
                    $item['program']['program_photo'] = asset($item['program']['program_photo']);
                }

                // Map provider (The person who owns the program/coach)
                $provider = [
                    'name' => '',
                    'profile_image' => null,
                    'role' => $booking->coach ? $booking->coach->role : null
                ];

                if ($booking->coach) {
                    $provider['name'] = $booking->coach->name . ' ' . $booking->coach->last_name;

                    if ($booking->coach->coachProfile && $booking->coach->coachProfile->coach_profile_pic) {
                        $provider['profile_image'] = asset($booking->coach->coachProfile->coach_profile_pic);
                    } elseif ($booking->coach->club && $booking->coach->club->club_logo) {
                        $provider['profile_image'] = asset($booking->coach->club->club_logo);
                    } elseif ($booking->coach->profile_image) {
                        $provider['profile_image'] = asset($booking->coach->profile_image);
                    }
                }

                $item['provider'] = $provider;

                return $item;
            })->values();

            $formattedActiveBooking = $activeBookingslast ? $activeBookingslast->toArray() : null;
            if ($formattedActiveBooking) {
                if (! empty($formattedActiveBooking['program']['program_photo'])) {
                    $formattedActiveBooking['program']['program_photo'] = asset($formattedActiveBooking['program']['program_photo']);
                }

                // Map provider for active booking
                $provider = [
                    'name' => '',
                    'profile_image' => null,
                    'role' => $activeBookingslast->coach ? $activeBookingslast->coach->role : null
                ];

                if ($activeBookingslast->coach) {
                    $provider['name'] = $activeBookingslast->coach->name . ' ' . $activeBookingslast->coach->last_name;

                    if ($activeBookingslast->coach->coachProfile && $activeBookingslast->coach->coachProfile->coach_profile_pic) {
                        $provider['profile_image'] = asset($activeBookingslast->coach->coachProfile->coach_profile_pic);
                    } elseif ($activeBookingslast->coach->club && $activeBookingslast->coach->club->club_logo) {
                        $provider['profile_image'] = asset($activeBookingslast->coach->club->club_logo);
                    } elseif ($activeBookingslast->coach->profile_image) {
                        $provider['profile_image'] = asset($activeBookingslast->coach->profile_image);
                    }
                }
                $formattedActiveBooking['provider'] = $provider;
            }

            return response()->json([
                'status' => true,
                'message' => 'Bookings retrieved successfully.',
                'data' => $formattedBookings,
                'active_booking' => $formattedActiveBooking,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve bookings: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function viewBookingDetails($program_id)
    {
        try {
            $program = ErProgram::query()
                ->with([
                    'coach',
                    'user:id,name,last_name,email,profile_image,role',
                    'user.club:id,user_id,club_name,club_logo,city,country',
                    'times',
                    'goals',
                    'reviews.user:id,name,last_name,profile_image',
                ])
                ->find($program_id);

            if (! $program) {
                return $this->notFound([], 'Program not found.', 404);
            }

            $ownerName = $program->user?->role === 'club'
                ? trim((string) ($program->user?->club?->club_name ?? $program->user?->name ?? ''))
                : trim(($program->coach?->name ?? $program->user?->name ?? '') . ' ' . ($program->coach?->last_name ?? $program->user?->last_name ?? ''));

            $ratingsCount = $program->reviews->count();
            $averageRating = round((float) $program->reviews->avg('rating'), 2);

            $ratingBreakdown = [];
            foreach ([5, 4, 3, 2, 1] as $star) {
                $total = $program->reviews->where('rating', $star)->count();
                $ratingBreakdown[] = [
                    'star' => $star,
                    'total' => $total,
                    'percent' => $ratingsCount > 0 ? round(($total / $ratingsCount) * 100, 2) : 0,
                ];
            }

            $recentFeedback = $program->reviews
                ->sortByDesc('created_at')
                ->take(10)
                ->values()
                ->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => (int) $review->rating,
                        'review' => $review->review,
                        'created_at' => $review->created_at,
                        'reviewer' => [
                            'id' => $review->user?->id,
                            'name' => trim(($review->user?->name ?? '') . ' ' . ($review->user?->last_name ?? '')),
                            'profile_image' => $review->user?->profile_image ? asset($review->user->profile_image) : null,
                        ],
                    ];
                });

            return $this->success([
                'program' => [
                    'id' => $program->id,
                    'program_name' => $program->program_name,
                    'program_type' => (string) ($program->program_type ?? 'one_one'),
                    'sport' => $program->sport,
                    'program_price' => (float) $program->program_price,
                    'discount_price' => (float) $program->discount_price,
                    'upto_age' => $program->upto_age,
                    'program_location' => $program->program_location,
                    'program_start' => optional($program->program_start)?->toDateString(),
                    'program_end' => optional($program->program_end)?->toDateString(),
                    'program_photo' => $program->program_photo ? asset($program->program_photo) : null,
                    'status' => $program->status,
                    'about_program' => $program->about_program,
                    'times' => $program->times->map(function ($time) {
                        return [
                            'id' => $time->id,
                            'time' => $time->time,
                            'slot_date' => optional($time->slot_date)?->toDateString(),
                            'start_time' => $time->start_time,
                            'end_time' => $time->end_time,
                            'is_available' => (bool) ($time->is_available ?? true),
                        ];
                    })->values(),
                    'goals' => $program->goals->map(function ($goal) {
                        return [
                            'id' => $goal->id,
                            'goal' => $goal->goal,
                        ];
                    })->values(),
                ],
                'coach' => [
                    'id' => $program->user?->role === 'club' ? $program->user?->id : $program->coach?->id,
                    'name' => $ownerName,
                    'email' => $program->user?->role === 'club' ? $program->user?->email : $program->coach?->email,
                    'profile_image' => $program->user?->role === 'club'
                        ? ($program->user?->club?->club_logo ? asset($program->user->club->club_logo) : ($program->user?->profile_image ? asset($program->user->profile_image) : null))
                        : ($program->coach?->coach_profile_pic ? asset($program->coach->coach_profile_pic) : null),
                    'bio' => $program->user?->role === 'club' ? null : $program->coach?->bio,
                    'title' => $program->user?->role === 'club'
                        ? []
                        : $program->coach?->coachingTitles->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'title' => $item->title,
                            ];
                        })->toArray(),
                ],
                'club' => $program->user?->role === 'club' ? [
                    'id' => $program->user?->club?->id,
                    'club_name' => $program->user?->club?->club_name,
                    'club_logo' => $program->user?->club?->club_logo ? asset($program->user->club->club_logo) : null,
                    'city' => $program->user?->club?->city,
                    'country' => $program->user?->club?->country,
                ] : null,

                'review_summary' => [
                    'average_rating' => $averageRating,
                    'total_reviews' => $ratingsCount,
                    'rating_breakdown' => $ratingBreakdown,
                ],
                'recent_feedback' => $recentFeedback,
            ], 'Program fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function coachProgramBookings(Request $request)
    {
        try {

            $user = Auth::guard('api')->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Authentication required.'
                ], 401);
            }

            if ($user->role !== 'coach') {
                return response()->json([
                    'status' => false,
                    'message' => 'Only coaches can view program bookings.'
                ], 403);
            }

            $query = ProgramBooking::with([
                'program',
                'athlete:id,name,last_name,image',
                'bookingTime',
                'parent:id,name,last_name,profile_image'
            ])
                ->where('coach_id', $user->id)
                ->whereHas('program', function ($q) {
                    $q->whereDate('program_end', '>=', now()->toDateString());
                })
                ->latest();

            // Status Filter
            if (
                $request->filled('status') &&
                in_array($request->status, [
                    'pending',
                    'confirmed',
                    'completed',
                    'cancelled',
                    'refunded'
                ])
            ) {
                $query->where('status', $request->status);
            }

            if($request->program_id){
                $query->where('program_id', $request->program_id);
            }

            $bookings = $query->get();


            if ($bookings->isEmpty()) {
                return $this->success([], 'No program bookings found.', 200);
            }

            $programPlayerCount= $bookings->groupBy('program_id')->map(function ($group) {
                return $group->count();
            });

            $formattedBookings = $bookings->map(function ($booking) {
                $bookingData = $booking->toArray();
                if ($booking->program && $booking->program->program_photo) {
                    $bookingData['program']['program_photo'] =
                        asset($booking->program->program_photo);
                }

                // Map provider (the person who booked)
                $provider = [
                    'name' => '',
                    'profile_image' => null
                ];

                if ($booking->athlete) {
                    $provider['name'] = $booking->athlete->name . ' ' . $booking->athlete->last_name;
                    $provider['profile_image'] = $booking->athlete->image ? asset($booking->athlete->image) : null;
                } elseif ($booking->parent) {
                    $provider['name'] = $booking->parent->name . ' ' . $booking->parent->last_name;
                    $provider['profile_image'] = $booking->parent->profile_image ? asset($booking->parent->profile_image) : null;
                }

                $bookingData['provider'] = $provider;

                return $bookingData;
            });

            return response()->json([
                'status' => true,
                'message' => 'Program bookings retrieved successfully.',
                'data' => $formattedBookings,
                'program_player_count' => $programPlayerCount??0,
            ], 200);
        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve program bookings.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function coachEarningsView(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return response()->json(['status' => false, 'message' => 'Authentication required.'], 401);
            }

            if ($user->role !== 'coach') {
                return response()->json([
                    'status' => false,
                    'message' => 'Only coaches can view earnings.',
                ], 403);
            }

            $paidBookings = ProgramBooking::query()
                ->with([
                    'program:id,program_name',
                    'athlete:id,name,last_name',
                ])
                ->where('coach_id', $user->id)
                ->where('payment_status', 'paid')
                ->where('status', '!=', 'cancelled')
                ->latest('id')
                ->get();

            $filter = strtolower((string) $request->query('filter', 'month'));
            $now = Carbon::now();

            if (in_array($filter, ['6month', '6months', 'sixmonth', 'sixmonths'], true)) {
                $filter = '6month';
            } elseif (! in_array($filter, ['month', 'year'], true)) {
                $filter = 'month';
            }

            if ($filter === 'year') {
                $periodStart = $now->copy()->startOfYear();
                $periodEnd = $now->copy()->endOfYear();
            } elseif ($filter === '6month') {
                $periodStart = $now->copy()->subMonths(5)->startOfMonth();
                $periodEnd = $now->copy()->endOfMonth();
            } else {
                $periodStart = $now->copy()->startOfMonth();
                $periodEnd = $now->copy()->endOfMonth();
            }

            $filteredBookings = $paidBookings->filter(function (ProgramBooking $booking) use ($periodStart, $periodEnd) {
                return $booking->created_at && $booking->created_at->between($periodStart, $periodEnd);
            });

            $activePrograms = $filteredBookings
                ->pluck('program_id')
                ->filter()
                ->unique()
                ->count();

            $totalEarnings = $filteredBookings->sum(function (ProgramBooking $booking) {
                return $this->calculatePayableFromBooking($booking);
            });

            $netEarnings = $filteredBookings->sum(function (ProgramBooking $booking) {
                return (float) ($booking->after_commission_amount ?? 0);
            });

            $platformFee = $filteredBookings->sum(function (ProgramBooking $booking) {
                return (float) ($booking->commission_amount ?? 0);
            });

            $earningsRows = $filteredBookings->map(function (ProgramBooking $booking) {
                $amount = (float) ($booking->amount ?? 0);
                $discount = (float) ($booking->discount ?? 0);
                $taxPercent = (float) ($booking->tax ?? 0);
                $subtotal = max(0, $amount - $discount);
                $taxAmount = round(($subtotal * $taxPercent) / 100, 2);
                $total = round($subtotal + $taxAmount, 2);

                return [
                    'booking_id' => $booking->id,
                    'client_name' => trim((string) ($booking->athlete?->name ?? '') . ' ' . (string) ($booking->athlete?->last_name ?? '')) ?: 'N/A',
                    'program_name' => $booking->program?->program_name ?? 'N/A',
                    'date' => optional($booking->created_at)?->toDateString(),
                    'amount' => round($amount, 2),
                    'hst' => $taxAmount,
                    'discount' => round($discount, 2),
                    'total' => $total,
                    'currency' => strtoupper((string) ($booking->currency ?: 'usd')),
                ];
            })->values();

            if ($filter === 'year') {
                $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                $growthBuckets = array_fill(0, 12, 0.0);

                foreach ($filteredBookings as $booking) {
                    if (! $booking->created_at) {
                        continue;
                    }

                    $index = (int) $booking->created_at->month - 1;
                    if ($index < 0 || $index > 11) {
                        continue;
                    }

                    $growthBuckets[$index] += $this->calculatePayableFromBooking($booking);
                }
            } elseif ($filter === '6month') {
                $labels = [];
                $growthBuckets = array_fill(0, 6, 0.0);
                $monthIndexMap = [];

                for ($i = 0; $i < 6; $i++) {
                    $m = $periodStart->copy()->addMonths($i);
                    $key = $m->format('Y-m');
                    $labels[] = $m->format('M');
                    $monthIndexMap[$key] = $i;
                }

                foreach ($filteredBookings as $booking) {
                    if (! $booking->created_at) {
                        continue;
                    }

                    $key = $booking->created_at->format('Y-m');
                    if (! array_key_exists($key, $monthIndexMap)) {
                        continue;
                    }

                    $growthBuckets[$monthIndexMap[$key]] += $this->calculatePayableFromBooking($booking);
                }
            } else {
                $labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'];
                $growthBuckets = array_fill(0, 5, 0.0);

                foreach ($filteredBookings as $booking) {
                    if (! $booking->created_at) {
                        continue;
                    }

                    $day = (int) $booking->created_at->day;
                    $index = min(4, (int) floor(($day - 1) / 7));
                    $growthBuckets[$index] += $this->calculatePayableFromBooking($booking);
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Coach earnings retrieved successfully.',
                'data' => [
                    'applied_filter' => $filter,
                    'summary' => [
                        'active_programs' => $activePrograms,
                        'total_earnings' => round((float) $totalEarnings, 2),
                        'net_earnings' => round((float) $netEarnings, 2),
                        'platform_fee' => round((float) $platformFee, 2),
                        'net_earnings_month' => round((float) $netEarnings, 2),
                        'platform_fee_month' => round((float) $platformFee, 2),
                    ],
                    'earnings' => $earningsRows,
                    'monthly_growth' => [
                        'labels' => $labels,
                        'values' => array_map(fn($value) => round((float) $value, 2), $growthBuckets),
                    ],
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve earnings: ' . $e->getMessage(),
            ], 500);
        }
    }
}