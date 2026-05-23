<?php

namespace App\Traits;

use App\Models\ErProgram;
use App\Support\AgeGroup;

trait ProgramProviderTrait
{
    /**
     * Resolve provider information (Coach or Club) for a given program.
     */
    public function resolveProvider(ErProgram $program): ?array
    {
        $provider = null;

        // Ensure relationships are loaded
        if (!$program->relationLoaded('user')) {
            $program->load('user.club');
        }
        if (!$program->relationLoaded('coach')) {
            $program->load('coach');
        }

        $user = $program->user;
        $coach = $program->coach;

        $is_program_maker = false;
        if (auth()->check()) {
            $is_program_maker = auth()->id() === $program->user_id;
        }

        if ($program->program_type === 'club' || ($user && $user->role === 'club')) {
            $club = $user->club ?? null;
            $provider = [
                'type' => 'club',
                'id' => $club->id ?? null,
                'profile_id' => $club->id ?? null,
                'user_mail' => $user->email ?? null,
                'user_id' => $user->id ?? null,

                'is_program_maker' => $is_program_maker,
                'name' => $club->club_name ?? $user->name ?? 'N/A',
                'image' => ($club && $club->club_logo) ? asset($club->club_logo) : ($user ? asset($user->profile_image) : null),

                'city' => $club->city ?? 'N/A',
                'email' => $club->email ?? 'N/A',
                'country' => $club->country ?? 'N/A',
                'is_verified' => true,
            ];
        } else {
            $provider = [
                'type' => 'coach',
                'id' => $coach->id ?? null,
                'profile_id' => $coach->id ?? null,
                'user_id' => $coach->user_id ?? $user->id ?? null,
                'is_program_maker' => $is_program_maker,
                'title' => $coach->coaching_title ?? null,
                'coach_title' => $coach->coaching_title ?? null,
                'name' => trim(($coach->name ?? ($user->name ?? '')) . ' ' . ($coach->last_name ?? ($user->last_name ?? ''))),
                'image' => ($coach && $coach->coach_profile_pic) ? asset($coach->coach_profile_pic) : ($user ? asset($user->profile_image) : null),
                'logo' => ($coach && $coach->coach_profile_pic) ? asset($coach->coach_profile_pic) : ($user ? asset($user->profile_image) : null),
                'designation' => $coach->current_role_display ?? 'Head Performance Coach',
                'email' => $coach->email ?? 'N/A',
                'user_mail' => $user->email ?? null,
                'city' => $coach->city ?? 'N/A',
                'is_verified' => true,
            ];
        }

        return $provider;
    }

    /**
     * Format program data with consistent structure.
     */
    public function formatProgramData(ErProgram $program): array
    {
        return [
            'id' => $program->id,
            'program_name' => $program->program_name,
            'program_type' => $program->program_type,
            'sport' => $program->sport,
            'sport_option' => $program->sportOption ? [
                'id' => $program->sportOption->id,
                'name' => $program->sportOption->name,
            ] : null,
            'price' => (float) $program->program_price,
            'discount_price' => (float) $program->discount_price,
            'location' => $program->program_location,
            'start_date' => optional($program->program_start)->toDateString(),
            'end_date' => optional($program->program_end)->toDateString(),
            'photo' => $program->program_photo ? asset($program->program_photo) : null,
            'about' => $program->about_program,
            'age_limit' => $program->upto_age,
            'age_group' => AgeGroup::resolveFromAge($program->upto_age),
            'provider' => $this->resolveProvider($program),
            'times' => $program->times->map(function ($time) {
                return [
                    'id' => $time->id,
                    'time' => $time->time,
                    'slot_date' => optional($time->slot_date)->toDateString(),
                    'start_time' => $time->start_time,
                    'end_time' => $time->end_time,
                    'is_available' => (bool) ($time->is_available ?? true)
                ];
            }),
            'goals' => $program->goals->map(function ($goal) {
                return [
                    'id' => $goal->id,
                    'goal' => $goal->goal
                ];
            }),
        ];
    }
}
