<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CoachPosition;
use App\Models\SportOption;

class Coach extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'last_name',
        'dob',
        'gender',
        'status',
        'user_id',
        'nationality',
        'email',
        'sports',
        'sport_option_id',
        'coaching_title',
        'current_role',
        'years_of_experience',
        'highest_education',
        'coaching_education',
        'coaching_philosophy',
        'player_centric_approach',
        'data_driving_training',
        'coach_profile_pic',
        'privacy_settings',
        'visible_reviews',
        'allow_parent_player_reviews',
        'city',
        'country',
        'country_id',
        'city_id',
        'facebook_link',
        'twitter_link',
        'instagram_link',

        'tiktok_link',
        'whatsapp_link',
        'preview',
    ];

    protected function casts(): array
    {
        return [
            'player_centric_approach' => 'boolean',
            'data_driving_training' => 'boolean',
            'visible_reviews' => 'boolean',
            'allow_parent_player_reviews' => 'boolean',
        ];
    }

    public function setDobAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['dob'] = null;

            return;
        }

        $this->attributes['dob'] = Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function coachingTitles()
    {
        return $this->hasMany(CoachingTitle::class, 'coach_id', 'id');
    }

    public function media()
    {
        return $this->hasMany(CoachMedia::class, 'coach_id', 'id');
    }

    public function erPrograms()
    {
        return $this->hasMany(ErProgram::class, 'coach_id', 'id');
    }

    public function programReviews()
    {
        return $this->hasManyThrough(
            ErProgramReview::class,
            ErProgram::class,
            'coach_id',
            'er_program_id',
            'id',
            'id'
        );
    }

    public function sportOption()
    {
        return $this->belongsTo(SportOption::class, 'sport_option_id', 'id');
    }

    public function currentPosition()
    {
        return $this->belongsTo(CoachPosition::class, 'current_role', 'id');
    }

    public function getSportsDisplayAttribute(): ?string
    {
        if ($this->relationLoaded('sportOption') && $this->sportOption?->name) {
            return $this->sportOption->name;
        }

        $rawSports = trim((string) ($this->sports ?? ''));

        if ($rawSports === '') {
            return null;
        }

        if (is_numeric($rawSports)) {
            return SportOption::query()->where('id', (int) $rawSports)->value('name') ?: $rawSports;
        }

        return $rawSports;
    }

    public function getCurrentRoleDisplayAttribute(): ?string
    {
        if ($this->relationLoaded('currentPosition') && $this->currentPosition?->name) {
            return $this->currentPosition->name;
        }

        $rawRole = $this->getAttribute('current_role');

        if ($rawRole === null || $rawRole === '') {
            return null;
        }

        if (is_numeric($rawRole)) {
            return CoachPosition::query()->where('id', (int) $rawRole)->value('name') ?: (string) $rawRole;
        }

        return (string) $rawRole;
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    public function playerVotes()
    {
        return $this->hasMany(PlayerVotingSyatem::class, 'coach_id', 'id');
    }

    public function toArray()
    {
        $data = parent::toArray();

        $data['current_role'] = $this->resolveCurrentRolePayload();

        return $data;
    }

    protected function resolveCurrentRolePayload(): ?array
    {
        $roleId = $this->getAttribute('current_role');

        if (! $roleId) {
            return null;
        }

        $position = $this->relationLoaded('currentPosition')
            ? $this->getRelation('currentPosition')
            : CoachPosition::query()->select('id', 'name')->find($roleId);

        if (! $position) {
            return [
                'id' => (int) $roleId,
                'name' => null,
            ];
        }

        return [
            'id' => (int) $position->id,
            'name' => $position->name,
        ];
    }
}
