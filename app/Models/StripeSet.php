<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeSet extends Model
{
    protected $fillable = [
        'user_id',
        'stripe_account_id',
        'details_submitted',
        'charges_enabled',
        'payouts_enabled',
        'onboarding_url',
    ];

    protected $casts = [
        'details_submitted' => 'boolean',
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
    ];
}
