<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AthleteProfiles extends Model
{
    protected $fillable = [
        'name',
        'last_name',
        'dob',
        'gender',
        'nationality',
        'email',
        'sports_selection',
        'sports',
        'jersey_number',
        'dominant_foot',
        'club_team',
        'parent_id',
        'user_id',
        'image',
        'primary_position',
        'secondary_position',
        'athlete_biography',
        'privacy_settings',
        'total_played_games',
        'goals',
        'assist',
        'yellow_cards',
        'red_cards',
        'clean_sheets',
        'total_saves',
        'city',
        'country',
    ];

    public function setSportsSelectionAttribute($value): void
    {
        $this->attributes['sports'] = $value;
    }

    public function getSportsSelectionAttribute(): ?string
    {
        return $this->attributes['sports'] ?? null;
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function parentAggrement()
    {
        return $this->hasOneThrough(
            ParentAggrements::class,
            User::class,
            'id',
            'user_id',
            'user_id',
            'id'
        );
    }

    public function strengths()
    {
        return $this->hasMany(PlayerStrength::class, 'player_profile_id', 'id');
    }

    public function mediaReels()
    {
        return $this->hasMany(PlayerMediaVideo::class, 'player_profile_id', 'id');
    }

    public function mediaLinks()
    {
        return $this->hasMany(PlayerMediaLink::class, 'player_profile_id', 'id');
    }

    public function achievements()
    {
        return $this->hasMany(PlayerAchievement::class, 'player_id', 'id');
    }

    public function endorsements()
    {
        return $this->hasMany(Endorse::class, 'athlete_profile_id', 'id');
    }

    public function primaryPosition()
    {
        return $this->belongsTo(PlayerPosition::class, 'primary_position', 'id');
    }

    public function secondaryPosition()
    {
        return $this->belongsTo(PlayerPosition::class, 'secondary_position', 'id');
    }

    public function recruitementApplies()
    {
        return $this->hasMany(RecruitementApply::class, 'child_id', 'id');
    }

    public function teamPlayers()
    {
        return $this->hasMany(TeamPlayer::class, 'child_id', 'id');
    }

    public function toArray()
    {
        $data = parent::toArray();

        $data['primary_position'] = $this->resolvePositionPayload('primary_position', 'primaryPosition');
        $data['secondary_position'] = $this->resolvePositionPayload('secondary_position', 'secondaryPosition');

        return $data;
    }

    protected function resolvePositionPayload(string $attribute, string $relation): ?array
    {
        $positionId = $this->getAttribute($attribute);

        if (! $positionId) {
            return null;
        }

        $position = $this->relationLoaded($relation)
            ? $this->getRelation($relation)
            : PlayerPosition::query()->select('id', 'name')->find($positionId);

        if (! $position) {
            return [
                'id' => (int) $positionId,
                'name' => null,
            ];
        }

        return [
            'id' => (int) $position->id,
            'name' => $position->name,
        ];
    }
}
