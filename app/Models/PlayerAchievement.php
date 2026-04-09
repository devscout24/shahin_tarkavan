<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerAchievement extends Model
{
    protected $table = 'player_achievement';

    protected $fillable = [
        'image',
        'title',
        'date_earned',
        'description',
        'player_id',
    ];

    public function player()
    {
        return $this->belongsTo(AthleteProfiles::class, 'player_id', 'id');
    }
}
