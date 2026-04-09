<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'city',
        'country', 
    ];

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

    public function currentPosition()
    {
        return $this->belongsTo(CoachPosition::class, 'current_role', 'id');
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