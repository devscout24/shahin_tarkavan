<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingDateAndTime;
use App\Models\ClubSubscription;
use App\Models\ErProgramTime;
use App\Models\ProgramBooking;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');

        $stripeSettings = Setting::query()
            ->where('group_name', 'stripe')
            ->pluck('value', 'key')
            ->toArray();

        // Use webhook secret from settings or env
        $secret = $stripeSettings['webhook_secret']
            ?? $stripeSettings['stripe_webhook_secret']
            ?? getenv('STRIPE_WEBHOOK_SECRET');

        try {
            // If secret is provided, verify signature. Otherwise, just decode (as fallback for existing setup)
            if ($secret && $sig) {
                $event = Webhook::constructEvent($payload, $sig, $secret);
            } else {
                $event = json_decode($payload);
                if (!$event) {
                    return response()->json(['error' => 'Invalid payload'], 400);
                }
            }
        } catch (\Exception $e) {
            Log::error('Stripe Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid webhook signature'], 400);
        }

        $eventType = isset($event->type) ? $event->type : null;
        $dataObject = isset($event->data->object) ? $event->data->object : null;

        if (!$eventType || !$dataObject) {
            return response()->json(['status' => 'ignored']);
        }

        Log::info('Stripe Webhook received:', ['type' => $eventType]);

        switch ($eventType) {
            case 'checkout.session.completed':
                $session = $dataObject;

                // Handle Program Booking (Payment mode)
                if (isset($session->mode) && $session->mode === 'payment') {
                    $this->handleProgramBooking($session);
                }
                // Handle Club Subscription (Subscription mode)
                elseif (isset($session->mode) && $session->mode === 'subscription') {
                    $this->handleClubSubscription($session);
                }
                break;

            case 'customer.subscription.created':
                $this->updateSubscriptionPeriod($dataObject);
                break;

            case 'customer.subscription.updated':
                $this->updateSubscriptionStatus($dataObject);
                break;

            case 'customer.subscription.deleted':
                $this->cancelSubscription($dataObject);
                break;

            case 'invoice.payment_succeeded':
                if (isset($dataObject->subscription)) {
                    ClubSubscription::where('stripe_subscription_id', $dataObject->subscription)
                        ->update(['status' => 'active']);
                }
                break;

            case 'invoice.payment_failed':
                if (isset($dataObject->subscription)) {
                    ClubSubscription::where('stripe_subscription_id', $dataObject->subscription)
                        ->update(['status' => 'past_due']);
                }
                break;
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle Program Booking logic from ProgramBookingController
     */
    private function handleProgramBooking($session)
    {
        $paymentIntentId = $session->payment_intent ?? null;
        $booking = ProgramBooking::query()
            ->with(['program', 'bookingTime', 'bookingDateTime'])
            ->where('stripe_session_id', $session->id)
            ->first();

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

    /**
     * Handle Club Subscription logic from SubscriptionPlanApiController
     */
    private function handleClubSubscription($session)
    {
        if (empty($session->metadata->club_id) || empty($session->metadata->subscription_plan_id)) {
            return;
        }

        // Cancel old subscription if this was an update
        if (!empty($session->metadata->old_subscription_id)) {
            try {
                $stripeSettings = Setting::query()
                    ->where('group_name', 'stripe')
                    ->pluck('value', 'key')
                    ->toArray();
                $stripeSecret = $stripeSettings['stripe_secret_key'] ?? null;
                if ($stripeSecret) {
                    \Stripe\Stripe::setApiKey($stripeSecret);
                    $oldSub = \Stripe\Subscription::retrieve($session->metadata->old_subscription_id);
                    $oldSub->cancel();
                }
            } catch (\Throwable $e) {
                Log::error('Failed to cancel old subscription during checkout: ' . $e->getMessage());
            }
        }

        ClubSubscription::updateOrCreate(
            ['club_id' => (int) $session->metadata->club_id],
            [
                'club_id' => $session->metadata->club_id,
                'subscription_plan_id' => $session->metadata->subscription_plan_id,
                'stripe_customer_id' => $session->customer ?? null,
                'stripe_subscription_id' => $session->subscription ?? null,
                'status' => 'active',
            ]
        );
    }

    private function updateSubscriptionPeriod($sub)
    {
        ClubSubscription::where('stripe_subscription_id', $sub->id)
            ->update([
                'current_period_start' => date('Y-m-d H:i:s', $sub->current_period_start),
                'current_period_end'   => date('Y-m-d H:i:s', $sub->current_period_end),
                'status'               => $sub->status,
            ]);
    }

    private function updateSubscriptionStatus($sub)
    {
        $priceId = $sub->items->data[0]->price->id ?? null;

        $updateData = [
            'status' => $sub->status,
            'current_period_start' => date('Y-m-d H:i:s', $sub->current_period_start),
            'current_period_end'   => date('Y-m-d H:i:s', $sub->current_period_end),
        ];

        if ($priceId) {
            $plan = \App\Models\SubscriptionPlan::where('stripe_price_id', $priceId)->first();
            if ($plan) {
                $updateData['subscription_plan_id'] = $plan->id;
            }
        }

        ClubSubscription::where('stripe_subscription_id', $sub->id)
            ->update($updateData);
    }

    private function cancelSubscription($sub)
    {
        ClubSubscription::where('stripe_subscription_id', $sub->id)
            ->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);
    }
}
