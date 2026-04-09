<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerMediaLink extends Model
{
    protected $table = 'player_media_link';

    protected $fillable = [
        'reels_video',
        'status',
        'image',
        'player_profile_id',
    ];

    public function playerProfile()
    {
        return $this->belongsTo(AthleteProfiles::class, 'player_profile_id', 'id');
    }
}
