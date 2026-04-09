<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubProfile extends Model
{
    protected $fillable = [
        'user_id',
        'club_name',
        'club_logo',
        'email',
        'phone',
        'sports_name',
        'city',
        'state',
        'country',
        'club_description',
        'privacy_settings',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
