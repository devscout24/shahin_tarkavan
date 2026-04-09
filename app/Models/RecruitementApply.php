<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitementApply extends Model
{
    protected $table = 'recruitement_applies';

    protected $fillable = [
        'recruitment_id',
        'team_id',
        'user_id',
        'child_id',
        'club_id',
        'type',
        'status',
    ];

    public function clubRecruitment()
    {
        return $this->belongsTo(ClubRecruitment::class, 'recruitment_id', 'id');
    }

    public function recruitment()
    {
        return $this->belongsTo(ClubRecruitment::class, 'recruitment_id', 'id');
    }

    public function team()
    {
        return $this->belongsTo(ClubTeam::class, 'team_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function child()
    {
        return $this->belongsTo(AthleteProfiles::class, 'child_id', 'id');
    }

    public function club()
    {
        return $this->belongsTo(User::class, 'club_id', 'id');
    }
}
