<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'discount_price',
        'billing_period',
        'trial_days',
        'stripe_product_id',
        'stripe_price_id',
        'is_stripe_synced',
        'features',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'trial_days' => 'integer',
            'is_stripe_synced' => 'boolean',
            'features' => 'array',
        ];
    }

    public function clubSubscriptions()
    {
        return $this->hasMany(ClubSubscription::class, 'subscription_plan_id', 'id');
    }
}
