<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
    ];

    public function teams()
    {
        return $this->hasMany(ClubTeam::class, 'competition_level_id', 'id');
    }
}
