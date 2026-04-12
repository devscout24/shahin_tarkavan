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
        'sports',
        'sports',
        'city',
        'state',
        'country',
        'club_description',
        'privacy_settings',
    ];

    public function setSportsNameAttribute($value): void
    {
        $this->attributes['sports'] = $value;
    }

    public function getSportsNameAttribute(): ?string
    {
        return $this->attributes['sports'] ?? null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
