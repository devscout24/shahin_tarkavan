<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchBid extends Model
{
    protected $fillable = [
        'match_id',
        'created_club_id',
        'requested_club_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function match()
    {
        return $this->belongsTo(ClubMatch::class, 'match_id', 'id');
    }

    public function createdClub()
    {
        return $this->belongsTo(ClubProfile::class, 'created_club_id', 'id');
    }

    public function requestedClub()
    {
        return $this->belongsTo(ClubProfile::class, 'requested_club_id', 'id');
    }
}
