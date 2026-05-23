<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerVotingSyatem extends Model
{
    protected $fillable = [
        'voted',
        'player_id',
        'vote_for_player_id',
        'coach_id',
        'vote_type',
    ];

    public function player()
    {
        return $this->belongsTo(AthleteProfiles::class, 'player_id', 'id');
    }

    public function voteForPlayer()
    {
        return $this->belongsTo(AthleteProfiles::class, 'vote_for_player_id', 'id');
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'id');
    }
}
