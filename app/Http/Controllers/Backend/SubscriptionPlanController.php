<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Yajra\DataTables\Facades\DataTables;

class SubscriptionPlanController extends Controller
{
    private function normalizeFeatures(?string $value): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) $value) ?: [])
            ->map(fn($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function payload(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'price' => (string) $plan->price,
            'discount_price' => (string) $plan->discount_price,
            'billing_period' => $plan->billing_period,
            'trial_days' => $plan->trial_days,
            'stripe_product_id' => $plan->stripe_product_id,
            'stripe_price_id' => $plan->stripe_price_id,
            'is_stripe_synced' => (bool) $plan->is_stripe_synced,
            'features' => $plan->features ?? [],
            'status' => $plan->status,
        ];
    }

    private function getStripeClient(): StripeClient
    {
        $secret = Setting::getValue('stripe', 'stripe_secret_key')
            ?: config('services.stripe.secret')
            ?: env('STRIPE_SECRET');

        if (!is_string($secret) || trim($secret) === '') {
            throw new \RuntimeException('Stripe secret key is not configured in Stripe settings.');
        }

        return new StripeClient($secret);
    }

    private function billingInterval(string $billingPeriod): ?array
    {
        return match ($billingPeriod) {
            'monthly' => ['interval' => 'month', 'interval_count' => 1],
            'yearly' => ['interval' => 'year', 'interval_count' => 1],
            default => null,
        };
    }

    private function stripeAmount(SubscriptionPlan $plan): int
    {
        $price = (float) $plan->price;
        $discount = (float) $plan->discount_price;

        $effective = $discount > 0 ? max(0, $price - $discount) : $price;

        return (int) round($effective * 100);
    }

    private function syncPlanToStripe(SubscriptionPlan $plan): void
    {
        $stripe = $this->getStripeClient();
        $currency = strtolower((string) (Setting::getValue('stripe', 'stripe_currency', 'usd') ?: 'usd'));
        $interval = $this->billingInterval((string) $plan->billing_period);

        $productPayload = [
            'name' => $plan->name,
            'metadata' => [
                'subscription_plan_id' => (string) $plan->id,
                'billing_period' => (string) $plan->billing_period,
                'status' => (string) $plan->status,
            ],
        ];

        $description = trim((string) ($plan->description ?? ''));
        if ($description !== '') {
            $productPayload['description'] = $description;
        }

        if ($plan->stripe_product_id) {
            $product = $stripe->products->update($plan->stripe_product_id, $productPayload);
        } else {
            $product = $stripe->products->create($productPayload);
        }

        $pricePayload = [
            'currency' => $currency,
            'unit_amount' => $this->stripeAmount($plan),
            'product' => $product->id,
            'metadata' => [
                'subscription_plan_id' => (string) $plan->id,
                'base_price' => (string) $plan->price,
                'discount_price' => (string) $plan->discount_price,
            ],
        ];

        if ($interval) {
            $pricePayload['recurring'] = $interval;
        }

        $price = $stripe->prices->create($pricePayload);

        $plan->forceFill([
            'stripe_product_id' => $product->id,
            'stripe_price_id' => $price->id,
            'is_stripe_synced' => true,
        ])->save();
    }

    public function index(): View
    {
        return view('Backend.subscriptions.index');
    }

    public function data(): JsonResponse
    {
        $plans = SubscriptionPlan::query()->latest();

        return DataTables::eloquent($plans)
            ->addColumn('billing_period_badge', function (SubscriptionPlan $plan) {
                $class = match ($plan->billing_period) {
                    'yearly' => 'bg-info',
                    'lifetime' => 'bg-dark',
                    default => 'bg-primary',
                };

                return '<span class="badge ' . $class . '">' . ucfirst((string) $plan->billing_period) . '</span>';
            })
            ->addColumn('status_badge', function (SubscriptionPlan $plan) {
                $class = $plan->status === 'active' ? 'bg-success' : 'bg-secondary';

                return '<span class="badge ' . $class . '">' . ucfirst((string) $plan->status) . '</span>';
            })
            ->addColumn('action', function (SubscriptionPlan $plan) {
                return '<div class="d-flex gap-1">'
                    . '<button type="button" class="btn btn-sm btn-icon btn-primary js-edit-plan" data-id="' . $plan->id . '" title="Edit"><i class="bi bi-pencil-square text-white"></i></button>'
                    . '<button type="button" class="btn btn-sm btn-icon btn-danger js-delete-plan" data-id="' . $plan->id . '" title="Delete"><i class="bi bi-trash text-white"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['billing_period_badge', 'status_badge', 'action'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:subscription_plans,name'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'billing_period' => ['required', Rule::in(['monthly', 'yearly', 'lifetime'])],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'features' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $slug = Str::slug($validated['name']);

        if (SubscriptionPlan::query()->where('slug', $slug)->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
        }

        try {
            DB::beginTransaction();

            $plan = SubscriptionPlan::query()->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'discount_price' => $validated['discount_price'] ?? 0,
                'billing_period' => $validated['billing_period'],
                'trial_days' => $validated['trial_days'] ?? 0,
                'features' => $this->normalizeFeatures($validated['features'] ?? null),
                'status' => $validated['status'],
                'is_stripe_synced' => false,
            ]);

            $this->syncPlanToStripe($plan);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Subscription plan created and synced to Stripe successfully.',
                'data' => $this->payload($plan->fresh()),
            ]);
        } catch (ApiErrorException | \Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 422);
        }
    }

    public function edit(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->payload($subscriptionPlan),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subscription_plans', 'name')->ignore($subscriptionPlan->id),
            ],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0'],
            'billing_period' => ['required', Rule::in(['monthly', 'yearly', 'lifetime'])],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'features' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        try {
            DB::beginTransaction();

            $subscriptionPlan->update([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'discount_price' => $validated['discount_price'] ?? 0,
                'billing_period' => $validated['billing_period'],
                'trial_days' => $validated['trial_days'] ?? 0,
                'features' => $this->normalizeFeatures($validated['features'] ?? null),
                'status' => $validated['status'],
                'is_stripe_synced' => false,
            ]);

            $this->syncPlanToStripe($subscriptionPlan);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Subscription plan updated and synced to Stripe successfully.',
                'data' => $this->payload($subscriptionPlan->fresh()),
            ]);
        } catch (ApiErrorException | \Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 422);
        }
    }

    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $subscriptionPlan->delete();

        return response()->json([
            'status' => true,
            'message' => 'Subscription plan deleted successfully.',
        ]);
    }
}
