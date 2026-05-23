<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CompetitionLevel;

class ClubTeam extends Model
{
    protected $fillable = [
        'club_id',
        'name',
        'age_group',
        'image',
        'competition_level_id',
        'gender',
    ];

    public function club()
    {
        return $this->belongsTo(User::class, 'club_id', 'id');
    }

    public function competitionLevel()
    {
        return $this->belongsTo(CompetitionLevel::class, 'competition_level_id', 'id');
    }

    public function recruitments()
    {
        return $this->hasMany(ClubRecruitment::class, 'club_team_id', 'id');
    }

    public function clubmatches()
    {
        return $this->hasMany(ClubMatch::class, 'club_team_id', 'id');
    }

    public function recruitement_applies()
    {
        return $this->hasMany(RecruitementApply::class, 'team_id', 'id');
    }

    public function teamPlayers()
    {
        return $this->hasMany(TeamPlayer::class, 'team_id', 'id');
    }
}
