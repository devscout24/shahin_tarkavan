<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramBooking extends Model
{
    protected $fillable = [
        'program_id',
        'athlete_profile_id',
        'parent_id',
        'coach_id',
        'booking_time_id',
        'booking_date',
        'status',
        'stripe_payment_intent_id',
        'stripe_session_id',
        'stripe_intend_id',
        'stripe_transfer_id',
        'payout_account_id',
        'amount',
        'tax',
        'discount',
        'after_commission_amount',
        'commission_amount',
        'payout_amount',
        'currency',
        'payment_status',
        'payout_status',
        'payout_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'after_commission_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'payout_amount' => 'decimal:2',
            'booking_date' => 'date',
            'payout_at' => 'datetime',
        ];
    }

    public function program()
    {
        return $this->belongsTo(ErProgram::class, 'program_id', 'id');
    }

    public function athlete()
    {
        return $this->belongsTo(AthleteProfiles::class, 'athlete_profile_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id', 'id');
    }

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function club()
    {
        return $this->belongsTo(User::class, 'club_id', 'id');
    }

    public function bookingTime()
    {
        return $this->belongsTo(ErProgramTime::class, 'booking_time_id', 'id');
    }

    public function bookingDateTime()
    {
        return $this->hasOne(BookingDateAndTime::class, 'program_booking_id', 'id');
    }
}