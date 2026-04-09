<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerStrength extends Model
{
    protected $table = 'player_strengths';

    protected $fillable = [
        'strength_type',
        'strength_name',
        'player_profile_id',
    ];

    public function playerProfile()
    {
        return $this->belongsTo(AthleteProfiles::class, 'player_profile_id', 'id');
    }

    public function endorsements()
    {
        return $this->hasMany(Endorse::class, 'player_strength_id', 'id');
    }
}
