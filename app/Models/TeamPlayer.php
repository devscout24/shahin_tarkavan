<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamPlayer extends Model
{
    protected $table = 'team_players';

    protected $fillable = [
        'team_id',
        'player_id',
        'parent_id',
        'coach_id',
        'club_id',
        'child_id',
    ];

    public function team()
    {
        return $this->belongsTo(ClubTeam::class, 'team_id', 'id');
    }

    public function player()
    {
        return $this->belongsTo(User::class, 'player_id', 'id');
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

    public function child()
    {
        return $this->belongsTo(AthleteProfiles::class, 'child_id', 'id');
    }
}
