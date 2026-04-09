<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachPosition extends Model
{
    protected $fillable = [
        'name',
        'status',
    ];

    public function coaches()
    {
        return $this->hasMany(Coach::class, 'current_role', 'id');
    }

    public function recruitments()
    {
        return $this->hasMany(ClubRecruitment::class, 'coach_position_id', 'id');
    }
}
