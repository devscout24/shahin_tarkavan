<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubMatch extends Model
{
    public $fillable = [
        'club_team_id',
        'available_date',
        'location',
        'field_opportunity',
        'opponent_club_id',
        'gender',
    ];

    public  function clubTeam()
    {
        return $this->belongsTo(ClubTeam::class, 'club_team_id', 'id');
    }

    public function opponentClub()
    {
        return $this->belongsTo(ClubProfile::class, 'opponent_club_id', 'id');
    }

    public function matchBids()
    {
        return $this->hasMany(MatchBid::class, 'match_id', 'id');
    }
}
