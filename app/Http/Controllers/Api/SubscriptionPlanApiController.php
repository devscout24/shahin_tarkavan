<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubSubscription;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Subscription;

class SubscriptionPlanApiController extends Controller
{
    use ApiResponse;

    private function resolveStripeSecret(): ?string
    {
        $stripeSettings = Setting::query()
            ->where('group_name', 'stripe')
            ->pluck('value', 'key')
            ->toArray();

        return $stripeSettings['stripe_secret_key'] ?? null;
    }

    public function index()
    {
        try {
            $plans = SubscriptionPlan::query()
                ->where('status', 'active')
                ->where('is_stripe_synced', true)
                ->get();

            return $this->success($plans, 'Subscription plans fetched successfully', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }


    public function show(int $planId): JsonResponse
    {
        try {
            $plan = SubscriptionPlan::query()
                ->where('id', $planId)
                ->where('status', 'active')
                ->where('is_stripe_synced', true)
                ->first();

            if (! $plan) {
                return $this->notFound([], 'Subscription plan not found.', 404);
            }

            return $this->success($plan, 'Subscription plan details fetched successfully', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }


    public function purchase(Request $request)
    {
        $validator = validator($request->all(), [
            'plan_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->errors($validator->errors(), 'Validation error', 422);
        }

        try {
            $plan = SubscriptionPlan::query()
                ->where('id', $request->plan_id)
                ->where('status', 'active')
                ->where('is_stripe_synced', true)
                ->first();

            if (! $plan) {
                return $this->notFound([], 'Subscription plan not found.', 404);
            }

            $user = Auth::guard('api')->user();

            if (! $user || $user->role !== 'club') {
                return $this->errors([], 'Only clubs can purchase subscription plans.', 403);
            }

            $stripeSecret = $this->resolveStripeSecret();

            if (! $stripeSecret) {
                return $this->errors([], 'Stripe secret key is missing in settings.', 422);
            }

            Stripe::setApiKey($stripeSecret);

            $existingSubscription = ClubSubscription::query()
                ->where('club_id', $user->id)
                ->latest('id')
                ->first();

            // Already subscribed check
            if (
                $existingSubscription &&
                $existingSubscription->subscription_plan_id === (int) $plan->id &&
                in_array((string) $existingSubscription->status, ['active', 'past_due', 'trialing'], true)
            ) {
                return $this->errors([], 'You are already subscribed to this plan.', 422);
            }

            // Prepare checkout session parameters
            $checkoutParams = [
                'payment_method_types' => ['card'],
                'mode' => 'subscription',
                'line_items' => [[
                    'price' => $plan->stripe_price_id,
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'club_id' => (string) $user->id,
                    'subscription_plan_id' => (string) $plan->id,
                ],
                'subscription_data' => [
                    'metadata' => [
                        'club_id' => (string) $user->id,
                        'subscription_plan_id' => (string) $plan->id,
                    ],
                ],
                'success_url' => url('/stripe/success'), // Consider using dedicated frontend URLs
                'cancel_url' => url('/stripe/cancel'),
            ];

            // If updating an existing subscription, use the same customer and track old sub for cancellation
            if ($existingSubscription && !empty($existingSubscription->stripe_customer_id) && $existingSubscription->status !== 'canceled') {
                $checkoutParams['customer'] = $existingSubscription->stripe_customer_id;
                $checkoutParams['metadata']['old_subscription_id'] = $existingSubscription->stripe_subscription_id;
                // Also add to subscription_data metadata so it's attached to the new subscription object
                $checkoutParams['subscription_data']['metadata']['old_subscription_id'] = $existingSubscription->stripe_subscription_id;
            } else {
                $checkoutParams['customer_email'] = $user->email;
            }

            $session = Session::create($checkoutParams);

            return $this->success([
                'checkout_url' => $session->url,
            ], 'Checkout session created successfully.', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }


    public function handleStripeWebhook(Request $request)
    {
        $event = json_decode($request->getContent());

        \Log::info('Stripe Webhook:', [$event]);

        switch ($event->type) {

            // ✅ STEP 1: Create subscription (from session)
            case 'checkout.session.completed':

                $session = $event->data->object;

                if ($session->mode !== 'subscription') {
                    break;
                }

                if (empty($session->metadata->club_id) || empty($session->metadata->subscription_plan_id)) {
                    break;
                }

                // Cancel old subscription if this was an update
                if (!empty($session->metadata->old_subscription_id)) {
                    try {
                        $stripeSecret = $this->resolveStripeSecret();
                        Stripe::setApiKey($stripeSecret);
                        $oldSub = Subscription::retrieve($session->metadata->old_subscription_id);
                        $oldSub->cancel();
                    } catch (\Throwable $e) {
                        \Log::error('Failed to cancel old subscription during checkout: ' . $e->getMessage());
                    }
                }

                ClubSubscription::updateOrCreate(
                    ['club_id' => (int) $session->metadata->club_id],
                    [
                        'club_id' => $session->metadata->club_id,
                        'subscription_plan_id' => $session->metadata->subscription_plan_id,
                        'stripe_customer_id' => $session->customer,
                        'stripe_subscription_id' => $session->subscription,
                        'status' => 'active',
                    ]
                );

                break;

            // ✅ STEP 2: Set period
            case 'customer.subscription.created':

                $sub = $event->data->object;

                ClubSubscription::where('stripe_subscription_id', $sub->id)
                    ->update([
                        'current_period_start' => date('Y-m-d H:i:s', $sub->current_period_start),
                        'current_period_end'   => date('Y-m-d H:i:s', $sub->current_period_end),
                        'status'               => $sub->status,
                    ]);

                break;

            // ✅ UPDATE subscription
            case 'customer.subscription.updated':

                $sub = $event->data->object;
                $priceId = $sub->items->data[0]->price->id ?? null;

                $updateData = [
                    'status' => $sub->status,
                    'current_period_start' => date('Y-m-d H:i:s', $sub->current_period_start),
                    'current_period_end'   => date('Y-m-d H:i:s', $sub->current_period_end),
                ];

                if ($priceId) {
                    $plan = SubscriptionPlan::where('stripe_price_id', $priceId)->first();
                    if ($plan) {
                        $updateData['subscription_plan_id'] = $plan->id;
                    }
                }

                ClubSubscription::where('stripe_subscription_id', $sub->id)
                    ->update($updateData);

                break;

            // ✅ CANCEL
            case 'customer.subscription.deleted':

                $sub = $event->data->object;

                ClubSubscription::where('stripe_subscription_id', $sub->id)
                    ->update([
                        'status' => 'canceled',
                        'canceled_at' => now(),
                    ]);

                break;

            // ✅ PAYMENT SUCCESS
            case 'invoice.payment_succeeded':

                $subId = $event->data->object->subscription;

                ClubSubscription::where('stripe_subscription_id', $subId)
                    ->update(['status' => 'active']);

                break;

            // ❌ PAYMENT FAILED
            case 'invoice.payment_failed':

                $subId = $event->data->object->subscription;

                ClubSubscription::where('stripe_subscription_id', $subId)
                    ->update(['status' => 'past_due']);

                break;
        }

        return response()->json(['status' => 'success']);
    }



    public function changePlan(Request $request)
    {
        $validator = validator($request->all(), [
            'plan_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->errors($validator->errors(), 'Validation error', 422);
        }

        return $this->purchase($request);
    }

    public function listSubscriptions(): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();
            $club = $user;

            $subscriptions = ClubSubscription::query()
                ->where('club_id', $club->id)
                ->with('subscriptionPlan')
                ->get();

            return $this->success($subscriptions, 'Subscriptions fetched successfully', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }
}
