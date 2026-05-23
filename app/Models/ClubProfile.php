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
        'sport_option_id',
        'city',
        'state',
        'country',
        'country_id',
        'city_id',
        'club_description',
        'privacy_settings',
        'facebook_link',
        'twitter_link',
        'instagram_link',

        'tiktok_link',
        'whatsapp_link',
        'preview',
    ];

    public function sportOption()
    {
        return $this->belongsTo(SportOption::class, 'sport_option_id', 'id');
    }

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

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }
}
