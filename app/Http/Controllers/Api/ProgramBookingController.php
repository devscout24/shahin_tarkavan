<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
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

    private function calculateCommissionAmount(float $grossAmount): float
    {
        $commission = Commission::query()
            ->where('status', 'active')
            ->latest('id')
            ->first();

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

    public function bookProgram(Request $request)
    {
        $user = Auth::guard('api')->user();

        try {
            if (! $user) {
                return response()->json(['status' => false, 'message' => 'Authentication required.'], 401);
            }

            $validator = Validator::make($request->all(), [
                'program_id' => 'required|integer|exists:er_programs,id',
                'athlete_profile_id' => 'required|integer|exists:athlete_profiles,id',
                'booking_time_id' => 'required|integer|exists:er_program_times,id',
                'amount' => 'nullable|numeric|min:0',
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

            $program = ErProgram::query()->with('coach:id,user_id')->find($request->program_id);
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

            $coachUserId = $program->coach?->user_id;
            if (! $coachUserId) {
                return response()->json(['status' => false, 'message' => 'Coach account not linked with this program.'], 422);
            }

            $bookingTimeExists = ErProgramTime::query()
                ->where('id', (int) $request->booking_time_id)
                ->where('er_program_id', $program->id)
                ->exists();

            if (! $bookingTimeExists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Selected booking time does not belong to this program.',
                ], 422);
            }

            $mainAmount = $request->filled('amount') ? (float) $request->amount : (float) $program->program_price;

            $defaultDiscount = 0.0;
            if ((float) $program->program_price > 0 && (float) $program->discount_price > 0 && (float) $program->discount_price < (float) $program->program_price) {
                $defaultDiscount = (float) $program->program_price - (float) $program->discount_price;
            }

            $discountAmount = $defaultDiscount;

            $countryCode = strtoupper((string) ($request->input('country_code') ?: 'CA'));

            // $taxPercent = $countryCode === 'CA' ? 13.0 : 0.0;
            $taxPercent = $request->input('tax_percent') !== null ? (float) $request->input('tax_percent') : ($countryCode === 'CA' ? 13.0 : 0.0);
            $amounts = $this->calculateBookingAmounts($mainAmount, $discountAmount, $taxPercent);
            $payableAmount = (float) $amounts['payable_amount'];

            $currency = strtolower((string) ($request->input('currency') ?: ($countryCode === 'CA' ? 'cad' : 'usd')));

            if ($payableAmount <= 0) {
                return response()->json(['status' => false, 'message' => 'Invalid amount.'], 422);
            }

            $stripeSecret = $this->resolveStripeSecret();
            if (! $stripeSecret) {
                return response()->json(['status' => false, 'message' => 'Stripe secret is not configured.'], 500);
            }

            Stripe::setApiKey($stripeSecret);

            $commissionAmount = $this->calculateCommissionAmount($payableAmount);
            $afterCommissionAmount = round($payableAmount - $commissionAmount, 2);

            $booking = ProgramBooking::query()->create([
                'program_id' => $program->id,
                'athlete_profile_id' => $athlete->id,
                'parent_id' => $parentId,
                'coach_id' => $coachUserId,
                'booking_time_id' => (int) $request->booking_time_id,
                'amount' => $amounts['amount'],
                'discount' => $amounts['discount'],
                'tax' => $amounts['tax'],
                'after_commission_amount' => (float) round($afterCommissionAmount, 2),
                'commission_amount' => (float) round($commissionAmount, 2),
                'currency' => $currency,
                'payment_status' => 'pending',
                'status' => 'pending',
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

            $booking = ProgramBooking::query()->where('stripe_session_id', $session->id)->first();

            if ($booking) {
                $booking->stripe_payment_intent_id = $paymentIntentId;
                $booking->payment_status = 'paid';
                $booking->status = 'pending';
                $booking->save();
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

            $booking = ProgramBooking::query()->find($id);
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

    public function AthleteParentlistBookings()
    {

        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return response()->json(['status' => false, 'message' => 'Authentication required.'], 401);
            }

            if (! in_array($user->role, ['parent', 'player'], true)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only parent or player can view bookings.',
                ], 403);
            }

            $baseQuery = ProgramBooking::query()
                ->with(['program', 'coach:id,name,last_name', 'bookingTime'])
                ->whereHas('program', function ($query) {
                    $query->whereDate('program_end', '>=', now()->toDateString());
                });

            if ($user->role === 'parent') {
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

                return $item;
            })->values();

            $formattedActiveBooking = $activeBookingslast ? $activeBookingslast->toArray() : null;
            if ($formattedActiveBooking && ! empty($formattedActiveBooking['program']['program_photo'])) {
                $formattedActiveBooking['program']['program_photo'] = asset($formattedActiveBooking['program']['program_photo']);
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
                    'times',
                    'goals',
                    'reviews.user:id,name,last_name,profile_image',
                ])
                ->find($program_id);

            if (! $program) {
                return $this->notFound([], 'Program not found.', 404);
            }

            $coachName = trim(($program->coach?->name ?? '') . ' ' . ($program->coach?->last_name ?? ''));

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
                    'id' => $program->coach?->id,
                    'name' => $coachName,
                    'email' => $program->coach?->email,
                    'profile_image' => $program->coach?->coach_profile_pic ? asset($program->coach->coach_profile_pic) : null,
                    'bio' => $program->coach?->bio,
                    'title' => $program->coach?->coachingTitles->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'title' => $item->title,
                        ];
                    })->toArray(),
                ],

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

    public function coachProgramBookings()
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return response()->json(['status' => false, 'message' => 'Authentication required.'], 401);
            }

            if ($user->role !== 'coach') {
                return response()->json([
                    'status' => false,
                    'message' => 'Only coaches can view program bookings.',
                ], 403);
            }

            $bookings = ProgramBooking::query()
                ->with(['program', 'athlete:id,name,last_name', 'bookingTime'])
                ->where('coach_id', $user->id)
                ->whereHas('program', function ($query) {
                    $query->whereDate('program_end', '>=', now()->toDateString());
                })
                ->latest()
                ->get();

            $formattedBookings = $bookings->map(function ($booking) {
                $item = $booking->toArray();
                if (! empty($item['program']['program_photo'])) {
                    $item['program']['program_photo'] = asset($item['program']['program_photo']);
                }

                return $item;
            })->values();

            return response()->json([
                'status' => true,
                'message' => 'Program bookings retrieved successfully.',
                'data' => $formattedBookings,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve program bookings: ' . $e->getMessage(),
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
