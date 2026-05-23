<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\ProgramBooking;
use App\Models\Setting;
use App\Models\StripeSet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Yajra\DataTables\Facades\DataTables;

class ProgramBookingPaymentController extends Controller
{
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

    private function calculateCommissionAndCoachPay(ProgramBooking $booking): array
    {
        $amount = (float) ($booking->amount ?? 0);
        $discount = (float) ($booking->discount ?? 0);
        $taxPercent = (float) ($booking->tax ?? 0);
        $subtotal = max(0.0, $amount - $discount);
        $taxAmount = ($subtotal * $taxPercent) / 100;
        $gross = round($subtotal + $taxAmount, 2);
        $ownerRole = strtolower((string) ($booking->coach?->role ?? 'all'));
        $ownerUserId = (int) ($booking->coach?->id ?? 0);
        $commission = $this->getActiveCommissionForRole($ownerRole, $ownerUserId);

        if (! $commission) {
            return [
                'commission_label' => 'N/A',
                'commission_amount' => 0.0,
                'coach_pay' => $gross,
            ];
        }

        $commissionAmount = 0.0;
        $type = strtolower((string) $commission->type);
        $amount = (float) $commission->amount;

        if ($type === 'percentage') {
            $commissionAmount = ($gross * $amount) / 100;
        } else {
            $commissionAmount = $amount;
        }

        $commissionAmount = max(0.0, min($gross, $commissionAmount));
        $coachPay = max(0.0, $gross - $commissionAmount);

        return [
            'commission_label' => $type === 'percentage'
                ? number_format($amount, 2) . '%'
                : 'Fixed ' . number_format($amount, 2),
            'commission_amount' => round($commissionAmount, 2),
            'coach_pay' => round($coachPay, 2),
        ];
    }

    private function getStripeClient(): StripeClient
    {
        $secret = Setting::getValue('stripe', 'stripe_secret_key')
            ?: getenv('STRIPE_SECRET');

        if (! is_string($secret) || trim($secret) === '') {
            throw new \RuntimeException('Stripe secret key is not configured in Stripe settings.');
        }

        return new StripeClient($secret);
    }

    public function index(): View
    {
        return view('Backend.programs.booking_payments');
    }

    public function data(): JsonResponse
    {
        $bookings = ProgramBooking::query()
            ->with([
                'program:id,program_name,program_location',
                'athlete:id,name,last_name',
                'parent:id,name,last_name,email',
                'coach:id,name,last_name,email,role',
                'coach.stripeSet:id,user_id,stripe_account_id,payouts_enabled',
            ])
            ->latest('id');

        return DataTables::eloquent($bookings)
            ->addColumn('program_name', function (ProgramBooking $booking) {
                return $booking->program?->program_name ?? 'N/A';
            })
            ->addColumn('athlete_name', function (ProgramBooking $booking) {
                return trim((string) ($booking->athlete?->name ?? '') . ' ' . (string) ($booking->athlete?->last_name ?? '')) ?: 'N/A';
            })
            ->addColumn('parent_name', function (ProgramBooking $booking) {
                return trim((string) ($booking->parent?->name ?? '') . ' ' . (string) ($booking->parent?->last_name ?? '')) ?: 'N/A';
            })
            ->addColumn('coach_name', function (ProgramBooking $booking) {
                return trim((string) ($booking->coach?->name ?? '') . ' ' . (string) ($booking->coach?->last_name ?? '')) ?: 'N/A';
            })
            ->addColumn('amount_display', function (ProgramBooking $booking) {
                $currency = strtoupper((string) ($booking->currency ?: 'usd'));
                $amount = (float) ($booking->amount ?? 0);
                $discount = (float) ($booking->discount ?? 0);
                $taxPercent = (float) ($booking->tax ?? 0);
                $subtotal = max(0.0, $amount - $discount);
                $taxAmount = ($subtotal * $taxPercent) / 100;
                $gross = round($subtotal + $taxAmount, 2);

                return $currency . ' ' . number_format($gross, 2);
            })
            ->addColumn('commission_display', function (ProgramBooking $booking) {
                $currency = strtoupper((string) ($booking->currency ?: 'usd'));
                $calc = $this->calculateCommissionAndCoachPay($booking);

                return $calc['commission_label'] . ' / ' . $currency . ' ' . number_format((float) $calc['commission_amount'], 2);
            })
            ->addColumn('coach_pay_display', function (ProgramBooking $booking) {
                $currency = strtoupper((string) ($booking->currency ?: 'usd'));
                $calc = $this->calculateCommissionAndCoachPay($booking);

                return $currency . ' ' . number_format((float) $calc['coach_pay'], 2);
            })
            ->addColumn('payment_badge', function (ProgramBooking $booking) {
                $status = (string) $booking->payment_status;
                $class = match ($status) {
                    'paid' => 'bg-success',
                    'failed' => 'bg-danger',
                    'refunded' => 'bg-warning text-dark',
                    default => 'bg-secondary',
                };

                return '<span class="badge ' . $class . '">' . ucfirst($status) . '</span>';
            })
            ->addColumn('payout_badge', function (ProgramBooking $booking) {
                $status = (string) ($booking->payout_status ?: 'pending');
                $class = match ($status) {
                    'paid' => 'bg-success',
                    'failed' => 'bg-danger',
                    default => 'bg-secondary',
                };

                return '<span class="badge ' . $class . '">' . ucfirst($status) . '</span>';
            })
            ->addColumn('action', function (ProgramBooking $booking) {
                $stripeAccountId = (string) ($booking->coach?->stripeSet?->stripe_account_id ?? '');
                $hasStripeAccount = $stripeAccountId !== '';
                $canPayout = $booking->payment_status === 'paid' && $booking->payout_status !== 'paid' && $hasStripeAccount;
                $calc = $this->calculateCommissionAndCoachPay($booking);
                $coachPay = (float) $calc['coach_pay'];

                $viewBtn = '<button class="btn btn-sm btn-outline-info js-view-booking" data-id="' . $booking->id . '">View</button>';

                if ($booking->payment_status === 'paid' && $booking->payout_status !== 'paid' && ! $hasStripeAccount) {
                    return '<div class="gap-1 d-flex">'
                        . $viewBtn
                        . '<button class="btn btn-sm btn-outline-warning" disabled>No Coach Stripe Account</button>'
                        . '</div>';
                }

                if (! $canPayout) {
                    return '<div class="gap-1 d-flex">'
                        . $viewBtn
                        . '<button class="btn btn-sm btn-outline-secondary" disabled>Payout Unavailable</button>'
                        . '</div>';
                }

                return '<div class="gap-1 d-flex">'
                    . $viewBtn
                    . '<button class="btn btn-sm btn-primary js-open-payout" '
                    . 'data-id="' . $booking->id . '" '
                    . 'data-amount="' . $coachPay . '" '
                    . 'data-stripe-account-id="' . e($stripeAccountId) . '" '
                    . 'data-currency="' . e((string) ($booking->currency ?: 'usd')) . '">'
                    . 'Payout to Coach'
                    . '</button>'
                    . '</div>';
            })
            ->rawColumns(['payment_badge', 'payout_badge', 'action'])
            ->toJson();
    }

    public function view(ProgramBooking $booking): JsonResponse
    {
        $booking->load([
            'program:id,program_name,program_location,program_start,program_end,program_photo',
            'athlete:id,name,last_name,email',
            'parent:id,name,last_name,email',
            'coach:id,name,last_name,email',
            'bookingTime:id,time',
        ]);

        $calc = $this->calculateCommissionAndCoachPay($booking);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'payment_status' => $booking->payment_status,
                'payout_status' => $booking->payout_status,
                'amount' => (float) $booking->amount,
                'discount' => (float) ($booking->discount ?? 0),
                'tax' => (float) ($booking->tax ?? 0),
                'tax_amount' => (float) round(max(0, ((float) $booking->amount - (float) ($booking->discount ?? 0)) * ((float) ($booking->tax ?? 0) / 100)), 2),
                'payable_amount' => (float) round(max(0, (float) $booking->amount - (float) ($booking->discount ?? 0)) + max(0, ((float) $booking->amount - (float) ($booking->discount ?? 0)) * ((float) ($booking->tax ?? 0) / 100)), 2),
                'currency' => strtoupper((string) ($booking->currency ?: 'usd')),
                'commission' => [
                    'label' => $calc['commission_label'],
                    'amount' => (float) $calc['commission_amount'],
                ],
                'coach_pay' => (float) $calc['coach_pay'],
                'program' => [
                    'id' => $booking->program?->id,
                    'name' => $booking->program?->program_name,
                    'location' => $booking->program?->program_location,
                    'start' => optional($booking->program?->program_start)?->toDateString(),
                    'end' => optional($booking->program?->program_end)?->toDateString(),
                    'photo' => ! empty($booking->program?->program_photo) ? asset($booking->program->program_photo) : null,
                ],
                'athlete' => [
                    'id' => $booking->athlete?->id,
                    'name' => trim((string) ($booking->athlete?->name ?? '') . ' ' . (string) ($booking->athlete?->last_name ?? '')),
                    'email' => $booking->athlete?->email,
                ],
                'parent' => [
                    'id' => $booking->parent?->id,
                    'name' => trim((string) ($booking->parent?->name ?? '') . ' ' . (string) ($booking->parent?->last_name ?? '')),
                    'email' => $booking->parent?->email,
                ],
                'coach' => [
                    'id' => $booking->coach?->id,
                    'name' => trim((string) ($booking->coach?->name ?? '') . ' ' . (string) ($booking->coach?->last_name ?? '')),
                    'email' => $booking->coach?->email,
                    'stripe_account_id' => $booking->coach?->stripeSet?->stripe_account_id,
                ],
                'booking_time' => $booking->bookingTime?->time,
                'stripe' => [
                    'session_id' => $booking->stripe_session_id,
                    'payment_intent_id' => $booking->stripe_payment_intent_id,
                    'transfer_id' => $booking->stripe_transfer_id,
                ],
            ],
        ]);
    }

    public function payout(Request $request, ProgramBooking $booking): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        if ($booking->payment_status !== 'paid') {
            return response()->json([
                'status' => false,
                'message' => 'Only paid bookings can be paid out.',
            ], 422);
        }

        if ($booking->payout_status === 'paid') {
            return response()->json([
                'status' => false,
                'message' => 'This booking is already paid out.',
            ], 422);
        }

        $calc = $this->calculateCommissionAndCoachPay($booking);
        $coachPay = (float) $calc['coach_pay'];

        $stripeAccountId = (string) (StripeSet::query()
            ->where('user_id', $booking->coach_id)
            ->value('stripe_account_id') ?? '');

        if ($stripeAccountId === '') {
            return response()->json([
                'status' => false,
                'message' => 'Coach Stripe account is not connected.',
            ], 422);
        }

        $payoutAmount = (float) ($validated['amount'] ?? $coachPay);
        if ($payoutAmount <= 0 || $payoutAmount > $coachPay) {
            return response()->json([
                'status' => false,
                'message' => 'Payout amount must be greater than 0 and not exceed coach payable amount.',
            ], 422);
        }

        try {
            $stripe = $this->getStripeClient();
            $currency = strtolower((string) ($booking->currency ?: 'usd'));

            $transfer = $stripe->transfers->create([
                'amount' => (int) round($payoutAmount * 100),
                'currency' => $currency,
                'destination' => $stripeAccountId,
                'description' => 'Coach payout for booking #' . $booking->id,
                'metadata' => [
                    'booking_id' => (string) $booking->id,
                    'program_id' => (string) $booking->program_id,
                    'coach_user_id' => (string) $booking->coach_id,
                ],
            ]);

            $booking->update([
                'payout_status' => 'paid',
                'stripe_transfer_id' => (string) $transfer->id,
                'payout_account_id' => $stripeAccountId,
                'payout_amount' => $payoutAmount,
                'payout_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payout sent successfully.',
                'data' => [
                    'booking_id' => $booking->id,
                    'transfer_id' => (string) $transfer->id,
                ],
            ]);
        } catch (ApiErrorException | \Throwable $e) {
            $booking->update([
                'payout_status' => 'failed',
            ]);

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

