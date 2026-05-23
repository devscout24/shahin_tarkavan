<?php

namespace App\Models;

use App\Models\RecruitementApply;
use Illuminate\Database\Eloquent\Model;

class ClubRecruitment extends Model
{
    protected $table = 'club_recruitments';
    protected $fillable = [
        'club_id',
        'player_position',
        'coach_position_id',
        'club_team_id',
        'recruitment_type',
        'gender',
        'start_date',
        'end_date',
        'experience',
        'description',
        'upto_age',
        'status'
    ];

    public function playerPosition()
    {
        return $this->belongsTo(PlayerPosition::class, 'player_position');
    }

    public function club()
    {
        return $this->belongsTo(User::class, 'club_id', 'id');
    }

    public function coachPosition()
    {
        return $this->belongsTo(CoachPosition::class, 'coach_position_id');
    }

    public function clubTeam()
    {
        return $this->belongsTo(ClubTeam::class, 'club_team_id');
    }

    public function getRecruitmentTypeLabelAttribute()
    {
        return $this->recruitment_type === 'player' ? 'Player' : 'Coach';
    }

    public function recruitement_apply()
    {
        return $this->hasMany(RecruitementApply::class, 'recruitment_id', 'id');
    }

    public function recruitementApplies()
    {
        return $this->hasMany(RecruitementApply::class, 'recruitment_id', 'id');
    }
}
