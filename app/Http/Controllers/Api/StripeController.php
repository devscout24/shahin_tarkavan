<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;

class StripeController extends Controller
{
    public function createStripeAccount(Request $request)
    {
        try {

            $user = Auth::guard('api')->user();

            if (! $user) {
                return response()->json(['status' => false, 'message' => 'Authentication required.'], 401);
            }

            $stripeSecret = $this->resolveStripeSecret();

            if (! $stripeSecret) {
                return response()->json(['status' => false, 'message' => 'Stripe secret not configured'], 500);
            }

            \Stripe\Stripe::setApiKey($stripeSecret);

            // 🔥 1. CREATE ACCOUNT
            $account = \Stripe\Account::create([
                'type' => 'express', // express OR standard OR custom
                'country' => 'CH',
                'email' => $user->email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],

                ],
            ]);

            // 🔥 2. CREATE ONBOARDING LINK
            $accountLink = \Stripe\AccountLink::create([
                'account' => $account->id,
                'refresh_url' => route('stripe.account.cancel'),
                'return_url' => route('stripe.account.success'),
                'type' => 'account_onboarding',
            ]);

            // 🔥 3. SAVE TO DATABASE
            \App\Models\StripeSet::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'stripe_account_id' => $account->id,
                    'onboarding_url' => $accountLink->url,
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Stripe account created successfully',
                'data' => [
                    'account_id' => $account->id,
                    'onboarding_url' => $accountLink->url,
                ]
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to create Stripe account',
                'error' => $e->getMessage(),
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
}
