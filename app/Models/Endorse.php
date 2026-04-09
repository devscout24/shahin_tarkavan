<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Endorse extends Model
{
    protected $fillable = [
        'player_strength_id',
        'athlete_profile_id',
        'strength_count',
        'endorced_by'
    ];

    public function playerStrength()
    {
        return $this->belongsTo(PlayerStrength::class, 'player_strength_id');
    }

    public function athleteProfile()
    {
        return $this->belongsTo(AthleteProfiles::class, 'athlete_profile_id');
    }

    public function endorsedBy()
    {
        return $this->belongsTo(User::class, 'endorced_by');
    }
}
