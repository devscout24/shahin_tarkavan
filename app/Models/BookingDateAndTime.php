<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingDateAndTime extends Model
{
    protected $table = 'booking_dateandtimes';

    protected $fillable = [
        'program_booking_id',
        'booking_time_id',
        'booking_date',
        'booking_type',
        'time_label',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
        ];
    }

    public function programBooking()
    {
        return $this->belongsTo(ProgramBooking::class, 'program_booking_id', 'id');
    }

    public function bookingTime()
    {
        return $this->belongsTo(ErProgramTime::class, 'booking_time_id', 'id');
    }
}