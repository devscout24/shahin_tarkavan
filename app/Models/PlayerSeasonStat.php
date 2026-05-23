<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerSeasonStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'athlete_profile_id',
        'season_year',
        'total_played_games',
        'total_played_time',
        'goals',
        'assist',
        'yellow_cards',
        'red_cards',
        'clean_sheets',
        'total_saves',
        'penalty_saves',
    ];

    public function athleteProfile()
    {
        return $this->belongsTo(AthleteProfiles::class, 'athlete_profile_id', 'id');
    }
}
