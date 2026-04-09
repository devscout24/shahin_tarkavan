<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
    ];

    public function primaryAthleteProfiles()
    {
        return $this->hasMany(AthleteProfiles::class, 'primary_position', 'id');
    }

    public function secondaryAthleteProfiles()
    {
        return $this->hasMany(AthleteProfiles::class, 'secondary_position', 'id');
    }

    public function recruitments()
    {
        return $this->hasMany(ClubRecruitment::class, 'player_position', 'id');
    }
}
