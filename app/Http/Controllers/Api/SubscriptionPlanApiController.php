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

     public function index(){
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
                    'plan_id' => 'required',
                ]);

                if ($validator->fails()) {
                    return $this->errors($validator->errors(), 'Validation error', 422);
                }

                try {
                    $plan = SubscriptionPlan::where('id', $request->plan_id)
                        ->where('status', 'active')
                        ->where('is_stripe_synced', true)
                        ->first();

                    if (!$plan) {
                        return $this->notFound([], 'Subscription plan not found.', 404);
                    }

                    $user = Auth::guard('api')->user();
                    $club = $user;


                                        $stripeSettings = Setting::query()
                                                ->where('group_name', 'stripe')
                                                ->pluck('value', 'key')
                                                ->toArray();

                                        $stripeSecret = $stripeSettings['stripe_secret_key'] ?? null;

                                        if (! $stripeSecret) {
                                                return $this->errors([], 'Stripe secret key is missing in settings.', 422);
                                        }

                                        Stripe::setApiKey($stripeSecret);

                    $session = Session::create([
                        'payment_method_types' => ['card'],
                        'mode' => 'subscription',
                        'customer_email' => $user->email,
                        'line_items' => [[
                            'price' => $plan->stripe_price_id,
                            'quantity' => 1,
                        ]],
                        'metadata' => [
                            'club_id' => $club->id,
                            'subscription_plan_id' => $plan->id,
                        ],
                        'success_url' => url('/payment-success'),
                        'cancel_url' => url('/payment-cancel'),
                    ]);

                    return response()->json([
                        'status' => true,
                        'checkout_url' => $session->url
                    ]);

                } catch (\Throwable $e) {
                    return $this->errors([], $e->getMessage(), 500);
                }
            }



   public function handleStripeWebhook(Request $request)
{
    $event = json_decode($request->getContent());

    switch ($event->type) {

        case 'customer.subscription.created':
            $sub = $event->data->object;

            ClubSubscription::updateOrCreate(
                ['stripe_subscription_id' => $sub->id],
                [
                    'club_id' => $sub->metadata->club_id,
                    'subscription_plan_id' => $sub->metadata->subscription_plan_id,
                    'stripe_customer_id' => $sub->customer,
                    'status' => $sub->status,
                    'current_period_start' => date('Y-m-d H:i:s', $sub->current_period_start),
                    'current_period_end' => date('Y-m-d H:i:s', $sub->current_period_end),
                ]
            );
            break;

        case 'customer.subscription.updated':
            $sub = $event->data->object;
            ClubSubscription::where('stripe_subscription_id', $sub->id)->update([
                'status' => $sub->status,
                'current_period_end' => date('Y-m-d H:i:s', $sub->current_period_end),
            ]);
            break;

        case 'customer.subscription.deleted':
            $sub = $event->data->object;
            ClubSubscription::where('stripe_subscription_id', $sub->id)->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);
            break;

        case 'invoice.payment_succeeded':
            $subId = $event->data->object->subscription;
            ClubSubscription::where('stripe_subscription_id', $subId)->update([
                'status' => 'active'
            ]);
            break;

        case 'invoice.payment_failed':
            $subId = $event->data->object->subscription;
            ClubSubscription::where('stripe_subscription_id', $subId)->update([
                'status' => 'past_due'
            ]);
            break;
    }

    return response()->json(['status' => 'success']);
}




public function changePlan(Request $request, int $planId)
{
    $plan = SubscriptionPlan::findOrFail($planId);
    $club = Auth::guard('api')->user()->club;

    $subscription = ClubSubscription::where('club_id', $club->id)
        ->where('status', 'active')
        ->firstOrFail();

    $stripeSettings = Setting::query()
        ->where('group_name', 'stripe')
        ->pluck('value', 'key')
        ->toArray();

    $stripeSecret = $stripeSettings['stripe_secret_key'] ?? null;
    if (! $stripeSecret) {
        return $this->errors([], 'Stripe secret key is missing in settings.', 422);
    }

    Stripe::setApiKey($stripeSecret);

    $stripeSub = Subscription::retrieve($subscription->stripe_subscription_id);

    Subscription::update($stripeSub->id, [
        'items' => [[
            'id' => $stripeSub->items->data[0]->id,
            'price' => $plan->stripe_price_id,
        ]],
        'proration_behavior' => 'create_prorations',
    ]);

    $subscription->update([
        'subscription_plan_id' => $plan->id,
    ]);

    return back()->with('success', 'Plan changed successfully!');
}

}
