<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\Coach;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchExploreController extends Controller
{
    use ApiResponse;

    public function list(Request $request)
    {
        $user = Auth::guard('api')->user();

        if (! $user) {
            return $this->unauthorized([], 'Authentication required.', 401);
        }

        if (! in_array($user->role, ['player', 'parent', 'coach'], true)) {
            return $this->forbidden([], 'Only player, parent, or coach can access this list.', 403);
        }

        $players = [];
        $coaches = [];
        $upcomingEvents = [];
        $clubs = [];

        if ($user->role == "parent" || $user->role == "player") {
            $players = AthleteProfiles::query()
                ->where('privacy_settings', 'public')
                ->with([
                    'user:id,name,last_name,profile_image,address',
                    'parent:id,name,last_name,profile_image,address',
                    'primaryPosition:id,name',
                ])
                ->orderByDesc('id')
                ->get()
                ->map(function (AthleteProfiles $profile) {
                  

                    return [
                        'type' => 'player',
                        'child_id' => $profile->parent_id ? $profile->id : null,
                        'player_id' => $profile->user_id,
                        'athlete_profile_id' => $profile->id,
                        'name' => trim((string) $profile->name . ' ' . (string) $profile->last_name),
                        'age' => $this->resolveAge($profile->dob),
                        'position' => $profile->primaryPosition?->name,
                        'jersey_number' => $profile->jersey_number,
                        'location' => $profile->country ? ', ' . $profile->country : '',
                        'parental_control_active' => ! is_null($profile->parent_id),
                        'games' => (int) ($profile->total_played_games ?? 0),
                        'goals' => (int) ($profile->goals ?? 0),
                        'assists' => (int) ($profile->assist ?? 0),
                        'profile_image' => $this->resolveProfileImage($profile),
                    ];
                })
                ->values();

            $coaches = Coach::query()
                ->where('status', 'approve')
                ->where('privacy_settings', 'public')
                ->with([
                    'user:id,name,last_name,profile_image,address',
                    'currentPosition:id,name',
                ])
                ->orderByDesc('id')
                ->get()
                ->map(function (Coach $coach) {
                    return [
                        'type' => 'coach',
                        'coach_id' => $coach->id,
                        'user_id' => $coach->user_id,
                        'name' => trim((string) $coach->name . ' ' . (string) $coach->last_name),
                        'age' => $this->resolveAge($coach->dob),
                        'location' => $coach->country ? ', ' . $coach->country : '',
                        'coaching_title' => $coach->currentPosition?->name ?? 'Coach',
                        'years_of_experience' => $coach->years_of_experience,
                        'sports' => $coach->sports,
                        'coaching_philosophy' => $coach->coaching_philosophy,
                        'player_centric_approach' => (bool) $coach->player_centric_approach,
                        'data_driving_training' => (bool) $coach->data_driving_training,
                        'profile_image' => $this->resolveCoachImage($coach),
                    ];
                })
                ->values();

                // $upcomingevents=
        }





        $allData = collect($players)->merge($coaches);

        return $this->success([
            'data' => $allData->values(),
            'total' => $allData->count(),
        ], 'Data fetched successfully', 200);
    }

    private function resolveAge($dob): ?int
    {
        if (! $dob) {
            return null;
        }

        try {
            return Carbon::parse($dob)->age;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveProfileImage(AthleteProfiles $profile): ?string
    {
        if (! empty($profile->image)) {
            return asset($profile->image);
        }

        if (! empty($profile->user?->profile_image)) {
            return asset($profile->user->profile_image);
        }

        if (! empty($profile->parent?->profile_image)) {
            return asset($profile->parent->profile_image);
        }

        return null;
    }

    private function resolveCoachImage(Coach $coach): ?string
    {
        if (! empty($coach->coach_profile_pic)) {
            return asset($coach->coach_profile_pic);
        }

        if (! empty($coach->user?->profile_image)) {
            return asset($coach->user->profile_image);
        }

        return null;
    }
}