<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\City;
use App\Models\ClubProfile;
use App\Models\ClubOrganization;
use App\Models\ClubRecruitment;
use App\Models\Coach;
use App\Models\Country;
use App\Models\ErProgram;
use App\Models\ErProgramReview;
use App\Models\TeamPlayer;
use App\Support\AgeGroup;
use App\Traits\ApiResponse;
use App\Traits\ProgramProviderTrait;
use App\Models\PlayerVotingSyatem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchExploreController extends Controller
{
    use ApiResponse, ProgramProviderTrait;

    public function list(Request $request)
    {
        $user = Auth::guard('api')->user();

        if (! $user) {
            return $this->unauthorized([], 'Authentication required.', 401);
        }

        if (! in_array($user->role, ['player', 'parent', 'coach', 'club'], true)) {
            return $this->forbidden([], 'Only player, parent, coach, or club can access this list.', 403);
        }

        $players = [];
        $coaches = [];
        $upcomingEvents = [];
        $clubs = [];
        $programs = [];
        $viewerContext = $this->resolveViewerContext($user);
        $viewerCoach = $user->role === 'coach'
            ? Coach::query()->where('user_id', $user->id)->latest('id')->first()
            : null;
        $viewerCoachPositionId = $viewerCoach?->current_role;
        $recruitmentType = $user->role === 'coach' ? 'coach' : ($user->role === 'club' ? null : 'player');
        $rawMinPrice = $request->input('min_price') ?? $request->query('min_price') ?? $request->input('min') ?? $request->query('min');
        $rawMaxPrice = $request->input('max_price') ?? $request->query('max_price') ?? $request->input('max') ?? $request->query('max');
        $rawCountry = $request->input('country') ?? $request->query('country');
        $rawCity = $request->input('city') ?? $request->query('city');

        $filterMinPrice = is_numeric($rawMinPrice) ? (float) $rawMinPrice : null;
        $filterMaxPrice = is_numeric($rawMaxPrice) ? (float) $rawMaxPrice : null;

        $filterCountryName = $rawCountry ? trim((string) $rawCountry) : null;
        $filterCityName = $rawCity ? trim((string) $rawCity) : null;

        $filterCountryId = $filterCountryName
            ? Country::query()->whereRaw('LOWER(name) = ?', [strtolower($filterCountryName)])->value('id')
            : null;

        $filterCityId = $filterCityName
            ? City::query()->whereRaw('LOWER(name) = ?', [strtolower($filterCityName)])->value('id')
            : null;

        if ($filterMinPrice !== null && $filterMaxPrice !== null && $filterMinPrice > $filterMaxPrice) {
            [$filterMinPrice, $filterMaxPrice] = [$filterMaxPrice, $filterMinPrice];
        }

        if ($user->role == "coach" || $user->role == "club") {
            $players = AthleteProfiles::query()
                ->whereIn('privacy_settings', ['public', 'coach_and_team'])
                ->when($filterCountryId || $filterCountryName, function (Builder $query) use ($filterCountryId, $filterCountryName) {
                    if ($filterCountryId) {
                        $query->where('country_id', $filterCountryId);

                        return;
                    }

                    $query->where('country', 'like', '%' . $filterCountryName . '%');
                })
                ->when($filterCityId || $filterCityName, function (Builder $query) use ($filterCityId, $filterCityName) {
                    if ($filterCityId) {
                        $query->where('city_id', $filterCityId);

                        return;
                    }

                    $query->where('city', 'like', '%' . $filterCityName . '%');
                })
                ->with([
                    'user:id,name,last_name,profile_image,address',
                    'parent:id,name,last_name,profile_image,address',
                    'primaryPosition:id,name',
                ])
                ->orderByDesc('id')
                ->get()
                ->map(function (AthleteProfiles $profile) {
                    $provencialCount = PlayerVotingSyatem::query()
                        ->where('vote_for_player_id', $profile->id)
                        ->where('vote_type', 'provencial')
                        ->count();

                    $professionalCount = PlayerVotingSyatem::query()
                        ->where('vote_for_player_id', $profile->id)
                        ->where('vote_type', 'professional')
                        ->count();

                    return [
                        'type' => 'player',
                        'sort_key' => optional($profile->created_at)?->timestamp ?? $profile->id,
                        'child_id' => $profile->parent_id ? $profile->id : null,
                        'player_id' => $profile->user_id,
                        'athlete_profile_id' => $profile->id,
                        'name' => trim((string) $profile->name . ' ' . (string) $profile->last_name),
                        'age' => $this->resolveAge($profile->dob),
                        'age_group' => $this->resolveAgeGroup($this->resolveAge($profile->dob)),
                        'position' => $profile->primaryPosition?->name,
                        'sports' => $profile->sports,
                        'jersey_number' => $profile->jersey_number,
                        'city_id' => $profile->city_id,
                        'country_id' => $profile->country_id,
                        'location' => $this->formatLocation($profile->city, $profile->country),
                        'parental_control_active' => ! is_null($profile->parent_id),
                        'games' => (int) ($profile->total_played_games ?? 0),
                        'total_played_time' => (int) ($profile->total_played_time ?? 0),
                        'goals' => (int) ($profile->goals ?? 0),
                        'assists' => (int) ($profile->assist ?? 0),
                        'profile_image' => $this->resolveProfileImage($profile),
                        'provencial_votes' => $provencialCount,
                        'professional_votes' => $professionalCount,
                    ];
                })
                ->values();

            $coaches = Coach::query()
                ->where('status', 'approve')
                ->where('privacy_settings', 'public')
                ->when($filterCountryId || $filterCountryName, function (Builder $query) use ($filterCountryId, $filterCountryName) {
                    if ($filterCountryId) {
                        $query->where('country_id', $filterCountryId);

                        return;
                    }

                    $query->where('country', 'like', '%' . $filterCountryName . '%');
                })
                ->when($filterCityId || $filterCityName, function (Builder $query) use ($filterCityId, $filterCityName) {
                    if ($filterCityId) {
                        $query->where('city_id', $filterCityId);

                        return;
                    }

                    $query->where('city', 'like', '%' . $filterCityName . '%');
                })
                ->with([
                    'user:id,name,last_name,profile_image,address',
                    'currentPosition:id,name',
                ])
                ->orderByDesc('id')
                ->get()
                ->map(function (Coach $coach) {
                    return [
                        'type' => 'coach',
                        'sort_key' => optional($coach->created_at)?->timestamp ?? $coach->id,
                        'coach_id' => $coach->id,
                        'user_id' => $coach->user_id,
                        'name' => trim((string) $coach->name . ' ' . (string) $coach->last_name),
                        'age' => $this->resolveAge($coach->dob),
                        'age_group' => $this->resolveAgeGroup($this->resolveAge($coach->dob)),
                        'location' => $this->formatLocation($coach->city, $coach->country),
                        'coaching_title' => $coach->currentPosition?->name ?? 'Coach',
                        'years_of_experience' => $coach->years_of_experience,
                        'sports' => $coach->sports,
                        'city_id' => $coach->city_id,
                        'country_id' => $coach->country_id,
                        'coaching_philosophy' => $coach->coaching_philosophy,
                        'player_centric_approach' => (bool) $coach->player_centric_approach,
                        'data_driving_training' => (bool) $coach->data_driving_training,
                        'profile_image' => $this->resolveCoachImage($coach),
                    ];
                })
                ->values();
        } elseif ($user->role == "parent" || $user->role == "player") {
            $players = AthleteProfiles::query()
                ->whereIn('privacy_settings', ['public', 'only_player'])
                ->when($filterCountryId || $filterCountryName, function (Builder $query) use ($filterCountryId, $filterCountryName) {
                    if ($filterCountryId) {
                        $query->where('country_id', $filterCountryId);

                        return;
                    }

                    $query->where('country', 'like', '%' . $filterCountryName . '%');
                })
                ->when($filterCityId || $filterCityName, function (Builder $query) use ($filterCityId, $filterCityName) {
                    if ($filterCityId) {
                        $query->where('city_id', $filterCityId);

                        return;
                    }

                    $query->where('city', 'like', '%' . $filterCityName . '%');
                })
                ->with([
                    'user:id,name,last_name,profile_image,address',
                    'parent:id,name,last_name,profile_image,address',
                    'primaryPosition:id,name',
                ])
                ->orderByDesc('id')
                ->get()
                ->map(function (AthleteProfiles $profile) {
                    $provencialCount = PlayerVotingSyatem::query()
                        ->where('vote_for_player_id', $profile->id)
                        ->where('vote_type', 'provencial')
                        ->count();

                    $professionalCount = PlayerVotingSyatem::query()
                        ->where('vote_for_player_id', $profile->id)
                        ->where('vote_type', 'professional')
                        ->count();

                    return [
                        'type' => 'player',
                        'child_id' => $profile->parent_id ? $profile->id : null,
                        'player_id' => $profile->user_id,
                        'athlete_profile_id' => $profile->id,
                        'name' => trim((string) $profile->name . ' ' . (string) $profile->last_name),
                        'age' => $this->resolveAge($profile->dob),
                        'age_group' => $this->resolveAgeGroup($this->resolveAge($profile->dob)),
                        'position' => $profile->primaryPosition?->name,
                        'sports' => $profile->sports,
                        'jersey_number' => $profile->jersey_number,
                        'city_id' => $profile->city_id,
                        'country_id' => $profile->country_id,
                        'location' => $this->formatLocation($profile->city, $profile->country),
                        'parental_control_active' => ! is_null($profile->parent_id),
                        'games' => (int) ($profile->total_played_games ?? 0),
                        'total_played_time' => (int) ($profile->total_played_time ?? 0),
                        'goals' => (int) ($profile->goals ?? 0),
                        'assists' => (int) ($profile->assist ?? 0),
                        'profile_image' => $this->resolveProfileImage($profile),
                        'provencial_votes' => $provencialCount,
                        'professional_votes' => $professionalCount,
                    ];
                })
                ->values();

            $coaches = Coach::query()
                ->where('status', 'approve')
                ->where('privacy_settings', 'public')
                ->when($filterCountryId || $filterCountryName, function (Builder $query) use ($filterCountryId, $filterCountryName) {
                    if ($filterCountryId) {
                        $query->where('country_id', $filterCountryId);

                        return;
                    }

                    $query->where('country', 'like', '%' . $filterCountryName . '%');
                })
                ->when($filterCityId || $filterCityName, function (Builder $query) use ($filterCityId, $filterCityName) {
                    if ($filterCityId) {
                        $query->where('city_id', $filterCityId);

                        return;
                    }

                    $query->where('city', 'like', '%' . $filterCityName . '%');
                })
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
                        'age_group' => $this->resolveAgeGroup($this->resolveAge($coach->dob)),
                        'location' => $this->formatLocation($coach->city, $coach->country),
                        'coaching_title' => $coach->currentPosition?->name ?? 'Coach',
                        'years_of_experience' => $coach->years_of_experience,
                        'sports' => $coach->sports,
                        'city_id' => $coach->city_id,
                        'country_id' => $coach->country_id,
                        'coaching_philosophy' => $coach->coaching_philosophy,
                        'player_centric_approach' => (bool) $coach->player_centric_approach,
                        'data_driving_training' => (bool) $coach->data_driving_training,
                        'profile_image' => $this->resolveCoachImage($coach),
                    ];
                })
                ->values();
        }

        if (empty($players)) {
            $players = [];
        } else {
            $players = collect($players)->values()->all();
        }
        if (empty($coaches)) {
            $coaches = [];
        } else {
            $coaches = collect($coaches)->values()->all();
        }

        $clubs = ClubProfile::query()
            ->when($user->role === 'coach', function (Builder $query) {
                $query->where('privacy_settings', 'coach_and_players');
            }, function (Builder $query) use ($user) {
                if ($user->role === 'club') {
                    $query->where('privacy_settings', 'public');
                    return;
                }

                $query->whereIn('privacy_settings', ['public', 'players', 'coach_and_players']);
            })
            ->when($filterCountryId || $filterCityId || $filterCountryName || $filterCityName, function (Builder $query) use ($filterCountryId, $filterCityId, $filterCountryName, $filterCityName) {
                $query->where(function (Builder $locationQuery) use ($filterCountryId, $filterCityId, $filterCountryName, $filterCityName) {
                    if ($filterCountryId) {
                        $locationQuery->where('country_id', $filterCountryId);
                    } elseif ($filterCountryName) {
                        $locationQuery->where('country', 'like', '%' . $filterCountryName . '%');
                    }

                    if ($filterCityId) {
                        if ($filterCountryId || $filterCountryName) {
                            $locationQuery->orWhere('city_id', $filterCityId);
                        } else {
                            $locationQuery->where('city_id', $filterCityId);
                        }
                    } elseif ($filterCityName) {
                        if ($filterCountryId || $filterCountryName) {
                            $locationQuery->orWhere('city', 'like', '%' . $filterCityName . '%');
                        } else {
                            $locationQuery->where('city', 'like', '%' . $filterCityName . '%');
                        }
                    }
                });
            })
            ->with(['user:id,name,last_name,profile_image'])
            ->orderByDesc('id')
            ->get()
            ->map(function (ClubProfile $club) {
                // Get organization type for this club
                $organization = ClubOrganization::query()
                    ->where('user_id', $club->user_id)
                    ->with('organizationType:id,name')
                    ->first();

                // Get coaches associated with this club's teams
                $clubCoaches = Coach::query()
                    ->where(function (Builder $query) use ($club) {
                        $query->whereHas('user.clubTeams', function (Builder $teamQuery) use ($club) {
                            $teamQuery->where('club_id', $club->user_id);
                        })->orWhere('user_id', $club->user_id);
                    })
                    ->with('currentPosition:id,name')
                    ->limit(5)
                    ->get()
                    ->map(function ($coach) {
                        return [
                            'coach_id' => $coach->id,
                            'name' => trim((string) ($coach->name ?? '') . ' ' . (string) ($coach->last_name ?? '')),
                            'coaching_title' => $coach->coaching_title,
                            'position' => $coach->currentPosition?->name,
                        ];
                    })
                    ->values();

                return [
                    'type' => 'club',
                    'sort_key' => optional($club->created_at)?->timestamp ?? $club->id,
                    'club_profile_id' => $club->id,
                    'club_id' => $club->user_id,
                    'club_name' => $club->club_name,
                    'sports_name' => $club->sports,
                    'city_id' => $club->city_id,
                    'country_id' => $club->country_id,
                    'location' => $this->formatLocation($club->city, $club->country),
                    'club_description' => $club->club_description,
                    'organization_type_id' => $organization?->organization_type_id,
                    'organization_type' => $organization?->organizationType?->name,
                    'coaches' => $clubCoaches,
                    'club_logo' => ! empty($club->club_logo) ? asset($club->club_logo) : null,
                    'profile_image' => ! empty($club->user?->profile_image) ? asset($club->user->profile_image) : null,
                ];
            })
            ->values();

        $programs = ErProgram::query()
            ->when($user->role === 'club', function (Builder $query) {
                $query->where('status', 'upcoming');
            }, function (Builder $query) {
                $query->whereIn('status', ['active', 'upcoming']);
            })
            ->when($filterMinPrice !== null, function (Builder $query) use ($filterMinPrice) {
                $query->where('program_price', '>=', $filterMinPrice);
            })
            ->when($filterMaxPrice !== null, function (Builder $query) use ($filterMaxPrice) {
                $query->where('program_price', '<=', $filterMaxPrice);
            })
            ->where(function (Builder $dateQuery) {
                $dateQuery->whereNull('program_end')
                    ->orWhereDate('program_end', '>=', Carbon::now()->toDateString());
            })
            ->when($viewerContext['location'], function (Builder $query) use ($viewerContext) {
                $query->where('program_location', 'like', '%' . $viewerContext['location'] . '%');
            })
            ->when($viewerContext['age'] !== null, function (Builder $query) use ($viewerContext) {
                $userAge = (int) $viewerContext['age'];
                $range = match (true) {
                    $userAge <= 8 => [0, 8],
                    $userAge <= 12 => [9, 12],
                    $userAge <= 17 => [13, 17],
                    $userAge <= 20 => [18, 20],
                    $userAge <= 30 => [21, 30],
                    default => [31, 999],
                };
                $query->where(function (Builder $ageQuery) use ($range) {
                    $ageQuery->whereNull('upto_age')
                        ->orWhereBetween('upto_age', [$range[0], $range[1]]);
                });
            })
            ->with(['coach:id,user_id,name,last_name,coach_profile_pic,city,country', 'user.club:id,user_id,club_name,club_logo,city,country', 'sportOption:id,name', 'times', 'goals'])
            ->orderBy('program_start')
            ->get()
            ->map(function (ErProgram $program) {
                $base = $this->formatProgramData($program);
                $base['type'] = 'program';
                $base['sort_key'] = optional($program->created_at)?->timestamp ?? $program->id;
                $base['program_id'] = $program->id;

                // Keep backward compatibility
                $base['coach_id'] = $program->coach_id;
                $base['coach_name'] = $base['provider']['name'] ?? '';
                $base['club_name'] = $base['provider']['name'] ?? '';
                $base['club_program_id'] = $program->id;

                return $base;
            })
            ->values();

        $upcomingEvents = ClubRecruitment::query()
            ->when($recruitmentType, function (Builder $query) use ($recruitmentType) {
                $query->where('recruitment_type', $recruitmentType);
            })
            ->where('status', 'active')
            ->where(function (Builder $dateQuery) {
                $now = Carbon::now()->toDateString();
                $dateQuery->where(function ($q) use ($now) {
                    $q->whereNull('start_date')
                        ->orWhereDate('start_date', '<=', $now);
                })
                    ->where(function ($q) use ($now) {
                        $q->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $now);
                    });
            })
            ->when($viewerContext['country_id'] || $viewerContext['city_id'] || $viewerContext['country'] || $viewerContext['city'], function (Builder $query) use ($viewerContext) {
                $query->whereHas('club.club', function (Builder $locationQuery) use ($viewerContext) {
                    if ($viewerContext['country_id']) {
                        $locationQuery->where('country_id', $viewerContext['country_id']);
                    } elseif ($viewerContext['country']) {
                        $locationQuery->where('country', 'like', '%' . $viewerContext['country'] . '%');
                    }

                    if ($viewerContext['city_id']) {
                        if ($viewerContext['country_id'] || $viewerContext['country']) {
                            $locationQuery->orWhere('city_id', $viewerContext['city_id']);
                        } else {
                            $locationQuery->where('city_id', $viewerContext['city_id']);
                        }
                    } elseif ($viewerContext['city']) {
                        if ($viewerContext['country_id'] || $viewerContext['country']) {
                            $locationQuery->orWhere('city', 'like', '%' . $viewerContext['city'] . '%');
                        } else {
                            $locationQuery->where('city', 'like', '%' . $viewerContext['city'] . '%');
                        }
                    }
                });
            })
            ->when($viewerContext['age'] !== null, function (Builder $query) use ($viewerContext) {
                $userAge = (int) $viewerContext['age'];
                $range = match (true) {
                    $userAge <= 8 => [0, 8],
                    $userAge <= 12 => [9, 12],
                    $userAge <= 17 => [13, 17],
                    $userAge <= 20 => [18, 20],
                    $userAge <= 30 => [21, 30],
                    default => [31, 999],
                };
                $query->where(function (Builder $ageQuery) use ($range) {
                    $ageQuery->whereNull('upto_age')
                        ->orWhereBetween('upto_age', [$range[0], $range[1]]);
                });
            })
            ->when($user->role === 'coach' && $viewerCoachPositionId, function (Builder $query) use ($viewerCoachPositionId) {
                $query->where(function (Builder $positionQuery) use ($viewerCoachPositionId) {
                    $positionQuery->where('coach_position_id', $viewerCoachPositionId)
                        ->orWhereNull('coach_position_id');
                });
            })
            ->with([
                'club:id,name,last_name,profile_image',
                'club.club:user_id,club_name,club_logo,sports,city,state,country',
                'clubTeam:id,club_id,name,age_group,image',
                'playerPosition:id,name',
                'coachPosition:id,name',
            ])
            ->orderBy('end_date')
            ->get()
            ->map(function (ClubRecruitment $event) {
                $clubProfile = $event->club?->club;

                return [
                    'type' => 'upcoming_event',
                    'sort_key' => optional($event->created_at)?->timestamp ?? $event->id,
                    'recruitment_id' => $event->id,
                    'club_id' => $event->club_id,
                    'club_team_id' => $event->club_team_id,
                    'team_name' => $event->clubTeam?->name,
                    'recruitment_type' => $event->recruitment_type,
                    'age_group' => AgeGroup::normalize($event->clubTeam?->age_group) ?? $this->resolveAgeGroup($event->upto_age),
                    'upto_age' => $event->upto_age,
                    'start_date' => $event->start_date
                        ? ($event->start_date instanceof \Carbon\CarbonInterface
                            ? $event->start_date->toDateTimeString()
                            : \Carbon\Carbon::parse($event->start_date)->toDateTimeString())
                        : null,
                    'end_date' => $event->end_date
                        ? ($event->end_date instanceof \Carbon\CarbonInterface
                            ? $event->end_date->toDateTimeString()
                            : \Carbon\Carbon::parse($event->end_date)->toDateTimeString())
                        : null,
                    'city_id' => $clubProfile?->city_id,
                    'country_id' => $clubProfile?->country_id,
                    'location' => $this->formatLocation($clubProfile?->city, $clubProfile?->country),
                    'player_position' => $event->playerPosition?->name,
                    'coach_position' => $event->coachPosition?->name,
                    'description' => $event->description,
                    'club_name' => $clubProfile?->club_name,
                    'sports' => $clubProfile?->sports,
                    'club_logo' => ! empty($clubProfile?->club_logo) ? asset($clubProfile->club_logo) : null,
                ];
            })
            ->values();

        // Exclude recruitment and upcoming programs from search/explore results.
        // Keep `$programs` intact for the separate `programs` response, but
        // do not merge recruitments or upcoming programs into the mixed feed.
        $upcomingEvents = [];

        $players = collect($players)->values()->all();
        $coaches = collect($coaches)->values()->all();
        $clubs = collect($clubs)->values()->all();
        $programs = collect($programs)->values()->all();
        $upcomingEvents = collect($upcomingEvents)->values()->all();

        // Include programs in the mixed feed, but keep recruitments excluded.
        $allData = collect($players)
            ->merge($coaches)
            ->merge($clubs)
            ->merge($programs)
            ->merge($upcomingEvents)
            ->sortByDesc('sort_key')
            ->values();

        $rawButtonType = $request->input('button_type') ?? $request->query('button_type');
        $buttonType = strtolower(trim((string) ($rawButtonType ?? '')));

        $filterLocation = strtolower(trim((string) ($request->input('location') ?? $request->query('location') ?? '')));
        $filterSports = strtolower(trim((string) ($request->input('sports') ?? $request->query('sports') ?? '')));
        $filterSearch = strtolower(trim((string) ($request->input('search') ?? $request->query('search') ?? '')));
        $filterAgeGroup = trim((string) (
            $request->input('age_group')
            ?? $request->query('age_group')
            ?? $request->input('age_grop')
            ?? $request->query('age_grop')
            ?? ''
        ));

        $buttonTypeMap = [
            'player' => 'players',
            'players' => 'players',
            'coach' => 'coaches',
            'coaches' => 'coaches',
            'club' => 'clubs',
            'clubs' => 'clubs',
            'program' => 'programs',
            'programs' => 'programs',
            'event' => 'upcoming_events',
            'events' => 'upcoming_events',
            'upcoming_event' => 'upcoming_events',
            'upcoming_events' => 'upcoming_events',
        ];

        $buttonTypeResponseAliases = [
            'players' => ['player', 'players'],
            'coaches' => ['coach', 'coaches'],
            'clubs' => ['club', 'clubs'],
            'programs' => ['program', 'programs'],
            'upcoming_events' => ['event', 'events', 'upcoming_event', 'upcoming_events'],
        ];

        $rawPaginationNumber = $request->input('pagination_number')
            ?? $request->query('pagination_number')
            ?? $request->input('per_page')
            ?? $request->query('per_page')
            ?? $request->input('page_size')
            ?? $request->query('page_size')
            ?? $request->input('pageSize')
            ?? $request->query('pageSize')
            ?? $request->input('limit')
            ?? $request->query('limit')
            ?? null;

        $paginationNumber = is_numeric($rawPaginationNumber) && (int) $rawPaginationNumber > 0
            ? (int) $rawPaginationNumber
            : null;

        $rawPage = $request->input('page') ?? $request->query('page') ?? 1;
        $currentPage = is_numeric($rawPage) && (int) $rawPage > 0 ? (int) $rawPage : 1;

        $buildPaginationMeta = function (int $total, int $perPage, int $page) use ($request): array {
            $perPage = max(1, $perPage);
            $lastPage = (int) max(1, ceil($total / $perPage));
            $page = max(1, min($page, $lastPage));
            $query = $request->query();

            $buildUrl = function (int $targetPage) use ($request, $query): string {
                $targetQuery = array_merge($query, ['page' => $targetPage]);
                $queryString = http_build_query($targetQuery);

                return $queryString !== ''
                    ? $request->url() . '?' . $queryString
                    : $request->url();
            };

            return [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage,
                'first_page_url' => $buildUrl(1),
                'last_page_url' => $buildUrl($lastPage),
                'next_page_url' => $page < $lastPage ? $buildUrl($page + 1) : null,
                'prev_page_url' => $page > 1 ? $buildUrl($page - 1) : null,
                'links' => [
                    [
                        'url' => $page > 1 ? $buildUrl($page - 1) : null,
                        'label' => '&laquo; Previous',
                        'active' => false,
                    ],
                    [
                        'url' => $buildUrl($page),
                        'label' => (string) $page,
                        'active' => true,
                    ],
                    [
                        'url' => $page < $lastPage ? $buildUrl($page + 1) : null,
                        'label' => 'Next &raquo;',
                        'active' => false,
                    ],
                ],
            ];
        };

        $paginateCollection = function ($items) use ($paginationNumber, $currentPage, $buildPaginationMeta): array {
            $collection = collect($items)->values();
            $total = $collection->count();

            if ($paginationNumber === null) {
                return [
                    'items' => $collection->map(fn(array $item) => $this->stripExploreItem($item))->values(),
                    'meta' => null,
                ];
            }

            $lastPage = (int) max(1, ceil($total / $paginationNumber));
            $normalizedPage = max(1, min($currentPage, $lastPage));
            $offset = ($normalizedPage - 1) * $paginationNumber;
            $pagedItems = $collection->slice($offset, $paginationNumber)->values();

            return [
                'items' => $pagedItems->map(fn(array $item) => $this->stripExploreItem($item))->values(),
                'meta' => $buildPaginationMeta($total, $paginationNumber, $normalizedPage),
            ];
        };

        // Apply location filter if provided
        if ($filterLocation) {
            $players = collect($players)->filter(function ($item) use ($filterLocation) {
                $location = strtolower($item['location'] ?? '');
                return strpos($location, $filterLocation) !== false;
            })->values()->all();

            $coaches = collect($coaches)->filter(function ($item) use ($filterLocation) {
                $location = strtolower($item['location'] ?? '');
                return strpos($location, $filterLocation) !== false;
            })->values()->all();

            $clubs = collect($clubs)->filter(function ($item) use ($filterLocation) {
                $location = strtolower($item['location'] ?? '');
                return strpos($location, $filterLocation) !== false;
            })->values()->all();

            $programs = collect($programs)->filter(function ($item) use ($filterLocation) {
                $location = strtolower($item['location'] ?? '');
                return strpos($location, $filterLocation) !== false;
            })->values()->all();

            $upcomingEvents = collect($upcomingEvents)->filter(function ($item) use ($filterLocation) {
                $location = strtolower($item['location'] ?? '');
                return strpos($location, $filterLocation) !== false;
            })->values()->all();

            $allData = collect($players)
                ->merge($coaches)
                ->merge($clubs)
                ->merge($programs)
                ->merge($upcomingEvents);
        }

        if ($filterCountryId !== null) {
            $players = collect($players)->where('country_id', $filterCountryId)->values()->all();
            $coaches = collect($coaches)->where('country_id', $filterCountryId)->values()->all();
            $clubs = collect($clubs)->where('country_id', $filterCountryId)->values()->all();
            $programs = collect($programs)->where('country_id', $filterCountryId)->values()->all();
            $upcomingEvents = collect($upcomingEvents)->where('country_id', $filterCountryId)->values()->all();

            $allData = collect($players)
                ->merge($coaches)
                ->merge($clubs)
                ->merge($programs)
                ->merge($upcomingEvents);
        }

        if ($filterCityId !== null) {
            $players = collect($players)->where('city_id', $filterCityId)->values()->all();
            $coaches = collect($coaches)->where('city_id', $filterCityId)->values()->all();
            $clubs = collect($clubs)->where('city_id', $filterCityId)->values()->all();
            $programs = collect($programs)->where('city_id', $filterCityId)->values()->all();
            $upcomingEvents = collect($upcomingEvents)->where('city_id', $filterCityId)->values()->all();

            $allData = collect($players)
                ->merge($coaches)
                ->merge($clubs)
                ->merge($programs)
                ->merge($upcomingEvents);
        }

        // Apply sports filter if provided
        if ($filterSports) {
            $sportsList = array_values(array_filter(array_map('trim', explode(',', $filterSports))));
            $sportsIdList = [];
            $sportsNameList = [];

            foreach ($sportsList as $sport) {
                if (is_numeric($sport)) {
                    $sportsIdList[] = (int) $sport;
                } else {
                    $sportsNameList[] = strtolower(trim((string) $sport));
                }
            }

            $matchesSports = function (?string $value) use ($sportsNameList): bool {
                $value = strtolower(trim((string) $value));
                if ($value === '' || empty($sportsNameList)) {
                    return false;
                }

                foreach ($sportsNameList as $sport) {
                    if ($sport !== '' && strpos($value, $sport) !== false) {
                        return true;
                    }
                }

                return false;
            };

            $matchesSportsId = function ($sportId) use ($sportsIdList): bool {
                if (empty($sportsIdList)) {
                    return false;
                }
                $sportId = is_numeric($sportId) ? (int) $sportId : null;
                return $sportId !== null && in_array($sportId, $sportsIdList, true);
            };

            $players = collect($players)->filter(function ($item) use ($matchesSports, $sportsNameList) {
                return ! empty($sportsNameList) ? $matchesSports($item['sports'] ?? null) : false;
            })->values()->all();

            $coaches = collect($coaches)->filter(function ($item) use ($matchesSports, $sportsNameList) {
                return ! empty($sportsNameList) ? $matchesSports($item['sports'] ?? null) : false;
            })->values()->all();

            $clubs = collect($clubs)->filter(function ($item) use ($matchesSports, $sportsNameList) {
                return ! empty($sportsNameList) ? $matchesSports($item['sports_name'] ?? null) : false;
            })->values()->all();

            $programs = collect($programs)->filter(function ($item) use ($matchesSports, $matchesSportsId, $sportsIdList, $sportsNameList) {
                if (! empty($sportsIdList) && $matchesSportsId($item['sport_option_id'] ?? null)) {
                    return true;
                }
                if (! empty($sportsNameList) && $matchesSports($item['sport'] ?? null)) {
                    return true;
                }
                return empty($sportsIdList) && empty($sportsNameList);
            })->values()->all();

            $upcomingEvents = collect($upcomingEvents)->filter(function ($item) use ($matchesSports, $sportsNameList) {
                return ! empty($sportsNameList) ? $matchesSports($item['sports'] ?? null) : false;
            })->values()->all();

            $allData = collect($players)
                ->merge($coaches)
                ->merge($clubs)
                ->merge($programs)
                ->merge($upcomingEvents);
        }

        // Apply keyword search filter if provided.
        if ($filterSearch) {
            $matchesSearchInItem = function (array $item, array $keys) use ($filterSearch): bool {
                foreach ($keys as $key) {
                    $value = strtolower(trim((string) ($item[$key] ?? '')));
                    if ($value !== '' && strpos($value, $filterSearch) !== false) {
                        return true;
                    }
                }

                return false;
            };

            $players = collect($players)->filter(function ($item) use ($matchesSearchInItem) {
                return $matchesSearchInItem((array) $item, [
                    'name',
                    'position',
                    'sports',
                    'location',
                    'age_group',
                    'jersey_number',
                ]);
            })->values()->all();

            $coaches = collect($coaches)->filter(function ($item) use ($matchesSearchInItem) {
                return $matchesSearchInItem((array) $item, [
                    'name',
                    'coaching_title',
                    'sports',
                    'coaching_philosophy',
                    'location',
                    'age_group',
                ]);
            })->values()->all();

            $clubs = collect($clubs)->filter(function ($item) use ($matchesSearchInItem) {
                return $matchesSearchInItem((array) $item, [
                    'club_name',
                    'sports_name',
                    'location',
                    'club_description',
                ]);
            })->values()->all();

            $programs = collect($programs)->filter(function ($item) use ($matchesSearchInItem) {
                return $matchesSearchInItem((array) $item, [
                    'type',
                    'program_name',
                    'sport',
                    'location',
                    'coach_name',
                    'club_name',
                    'age_group',
                    'program_price',
                ]);
            })->values()->all();

            $upcomingEvents = collect($upcomingEvents)->filter(function ($item) use ($matchesSearchInItem) {
                return $matchesSearchInItem((array) $item, [
                    'team_name',
                    'recruitment_type',
                    'age_group',
                    'location',
                    'player_position',
                    'coach_position',
                    'description',
                    'club_name',
                    'sports',
                ]);
            })->values()->all();

            $allData = collect($players)
                ->merge($coaches)
                ->merge($clubs)
                ->merge($programs)
                ->merge($upcomingEvents);
        }

        // Apply inclusive program price range filter as final safeguard.
        if ($filterMinPrice !== null || $filterMaxPrice !== null) {
            $programs = collect($programs)->filter(function ($item) use ($filterMinPrice, $filterMaxPrice) {
                $price = isset($item['program_price']) && is_numeric($item['program_price'])
                    ? (float) $item['program_price']
                    : null;

                if ($price === null) {
                    return false;
                }

                if ($filterMinPrice !== null && $price < $filterMinPrice) {
                    return false;
                }

                if ($filterMaxPrice !== null && $price > $filterMaxPrice) {
                    return false;
                }

                return true;
            })->values()->all();

            $allData = collect($players)
                ->merge($coaches)
                ->merge($clubs)
                ->merge($programs)
                ->merge($upcomingEvents);
        }

        // Apply age group filter if provided
        if ($filterAgeGroup) {
            $matchesAgeGroup = fn($item) => AgeGroup::matchesFilter($filterAgeGroup, (array) $item);

            $players = collect($players)->filter($matchesAgeGroup)->values()->all();
            $coaches = collect($coaches)->filter($matchesAgeGroup)->values()->all();
            $programs = collect($programs)->filter($matchesAgeGroup)->values()->all();
            $upcomingEvents = collect($upcomingEvents)->filter($matchesAgeGroup)->values()->all();

            // Clubs do not have age-group metadata, so exclude them when age-group filter is active.
            $clubs = [];

            $allData = collect($players)
                ->merge($coaches)
                ->merge($clubs)
                ->merge($programs)
                ->merge($upcomingEvents);
        }

        $allData = collect($allData)
            ->sortByDesc('sort_key')
            ->values();

        if ($buttonType !== '' && $buttonType !== null) {
            $resolvedSection = $buttonTypeMap[$buttonType] ?? null;

            if (! $resolvedSection) {
                return $this->validationError([
                    'button_type' => ["Invalid button_type: '{$buttonType}'. Valid options: player, coach, club, program, event."],
                ], 'Validation failed', 422);
            }

            $sectionItems = match ($resolvedSection) {
                'players' => collect($players),
                'coaches' => collect($coaches),
                'clubs' => collect($clubs),
                'programs' => collect($programs),
                'upcoming_events' => collect($upcomingEvents),
                default => collect(),
            };

            $sectionItems = $sectionItems->sortByDesc('sort_key')->values();

            $paginatedSection = $paginateCollection($sectionItems);
            $sectionItems = $paginatedSection['items'];

            $sectionResponseKeys = $buttonTypeResponseAliases[$resolvedSection] ?? [$resolvedSection];
            $sectionPayload = [];
            // To prevent data duplication in the final merge, we don't repeat the main data array
            // when button_type is specified. We use the 'data' key as the source of truth.
            /*
            foreach ($sectionResponseKeys as $responseKey) {
                $sectionPayload[$responseKey] = $sectionItems->values();
            }
            */

            return $this->success(array_merge([
                'viewer' => [
                    'age' => $viewerContext['age'],
                    'age_group' => $viewerContext['age_group'],
                    'city' => $viewerContext['city'],
                    'country' => $viewerContext['country'],
                ],
                'filters' => [
                    'button_type' => $buttonType,
                    'search' => $filterSearch ?: null,
                    'location' => $filterLocation ?: null,
                    'sports' => $filterSports ?: null,
                    'age_group' => $filterAgeGroup ?: null,
                    'min_price' => $filterMinPrice,
                    'max_price' => $filterMaxPrice,
                    'pagination_number' => $paginationNumber,
                    'page' => $currentPage,
                ],
                'button_type' => $buttonType,
                'data' => $sectionItems->values(),
                'total' => $paginatedSection['meta']['total'] ?? $sectionItems->count(),
                'pagination' => $paginatedSection['meta'],
                'age_group_counts' => [
                    $resolvedSection => $this->buildLabelCounts($sectionItems, 'age_group'),
                ],
            ], $sectionPayload), 'Data fetched successfully', 200);
        }

        $paginatedAllData = $paginateCollection($allData);
        $allDataItems = $paginatedAllData['items'];

        $ageGroupCounts = [
            'players' => $this->buildLabelCounts(collect($players), 'age_group'),
            'coaches' => $this->buildLabelCounts(collect($coaches), 'age_group'),
            'programs' => $this->buildLabelCounts(collect($programs), 'age_group'),
            'upcoming_events' => $this->buildLabelCounts(collect($upcomingEvents), 'age_group'),
        ];

        return $this->success([
            'viewer' => [
                'age' => $viewerContext['age'],
                'age_group' => $viewerContext['age_group'],
                'city' => $viewerContext['city'],
                'country' => $viewerContext['country'],
            ],
            'filters' => [
                'search' => $filterSearch ?: null,
                'location' => $filterLocation ?: null,
                'country' => $filterCountryName ?: null,
                'city' => $filterCityName ?: null,
                'sports' => $filterSports ?: null,
                'age_group' => $filterAgeGroup ?: null,
                'min_price' => $filterMinPrice,
                'max_price' => $filterMaxPrice,
                'pagination_number' => $paginationNumber,
                'page' => $currentPage,
            ],
            'data' => $allDataItems->values(),
            // 'players' => collect($players)->values(),
            // 'coaches' => collect($coaches)->values(),
            // 'clubs' => $clubs,
            // 'programs' => $programs,
            'upcoming_events' => $upcomingEvents,
            'age_group_counts' => $ageGroupCounts,
            'total' => $allData->count(),
            'pagination' => $paginatedAllData['meta'],
        ], 'Data fetched successfully', 200);
    }

    private function resolveViewerContext($user): array
    {
        $city = null;
        $country = null;
        $cityId = null;
        $countryId = null;
        $age = null;

        if ($user->role === 'player') {
            $profile = AthleteProfiles::query()->where('user_id', $user->id)->latest('id')->first();
            $city = $profile?->city;
            $country = $profile?->country;
            $cityId = $profile?->city_id;
            $countryId = $profile?->country_id;
            $age = $this->resolveAge($profile?->dob);
        } elseif ($user->role === 'coach') {
            $profile = Coach::query()->where('user_id', $user->id)->latest('id')->first();
            $city = $profile?->city;
            $country = $profile?->country;
            $cityId = $profile?->city_id;
            $countryId = $profile?->country_id;
            $age = $this->resolveAge($profile?->dob);
        } elseif ($user->role === 'parent') {
            $profile = AthleteProfiles::query()->where('parent_id', $user->id)->latest('id')->first();
            $city = $profile?->city ?? $user->city?->name;
            $country = $profile?->country ?? $user->country?->name;
            $cityId = $profile?->city_id ?? $user->city_id;
            $countryId = $profile?->country_id ?? $user->country_id;
            $age = $this->resolveAge($profile?->dob);
        }

        if ($age === null) {
            $age = $this->resolveAge($user->dob ?? null);
        }

        $city = $this->normalizeLocationValue($city);
        $country = $this->normalizeLocationValue($country);

        if (! $country && $countryId) {
            $country = Country::query()->where('id', $countryId)->value('name');
        }

        if (! $city && $cityId) {
            $city = City::query()->where('id', $cityId)->value('name');
        }

        return [
            'city_id' => $cityId,
            'city' => $city,
            'country_id' => $countryId,
            'country' => $country,
            'location' => $city ?: $country,
            'age' => $age,
            'age_group' => $this->resolveAgeGroup($age),
        ];
    }

    private function resolveAgeGroup(?int $age): ?string
    {
        return AgeGroup::resolveFromAge($age);
    }

    private function formatLocation(?string $city, ?string $country): ?string
    {
        $city = $this->normalizeLocationValue($city);
        $country = $this->normalizeLocationValue($country);

        if ($city && $country) {
            return $city . ', ' . $country;
        }

        return $city ?: $country;
    }

    private function normalizeLocationValue(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function buildLabelCounts($items, string $key): array
    {
        return $items
            ->map(function ($item) use ($key) {
                return $item[$key] ?? null;
            })
            ->filter()
            ->countBy()
            ->map(function ($total, $label) {
                return [
                    'age_group' => (string) $label,
                    'total' => (int) $total,
                ];
            })
            ->values()
            ->all();
    }

    private function stripExploreItem(array $item): array
    {
        unset($item['sort_key']);

        return $item;
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
    //////////////////////////end///////////////

    public function viewexploreprofile(Request $request)
    {
        try {
            $viewer = Auth::guard('api')->user();

            if (! $viewer) {
                return $this->unauthorized([], 'Authentication required.', 401);
            }

            $profileType = strtolower(trim((string) (
                $request->input('profile_type')
                ?? $request->query('profile_type')
                ?? $request->input('type')
                ?? $request->query('type')
                ?? ''
            )));

            if ($profileType === '') {
                return $this->validationError([
                    'profile_type' => ['profile_type is required. Use player, coach, or club.'],
                ], 'Validation failed', 422);
            }

            if (in_array($profileType, ['event', 'events', 'program', 'programs', 'upcoming_event', 'upcoming_events'], true)) {
                return $this->success([], 'No profile view available for event/program type.', 200);
            }

            $profileId = $request->input('profile_id')
                ?? $request->query('profile_id')
                ?? $request->input('id')
                ?? $request->query('id');

            if (! is_numeric($profileId)) {
                return $this->validationError([
                    'profile_id' => ['Valid profile id is required.'],
                ], 'Validation failed', 422);
            }

            if ($profileType === 'player') {
                $profile = AthleteProfiles::query()
                    ->with(['strengths', 'mediaReels', 'mediaLinks', 'achievements', 'primaryPosition:id,name', 'secondaryPosition:id,name'])
                    ->where('id', (int) $profileId)
                    ->first();

                if (! $profile) {
                    return $this->notFound([], 'Profile not found', 404);
                }

                if (! $this->canViewAthleteProfile($viewer, $profile)) {
                    return $this->forbidden([], 'This player profile is private.', 403);
                }

                return $this->success($profile, 'Player data fetched successfully', 200);
            }

            if ($profileType === 'coach') {
                $coach = Coach::query()
                    ->with(['coachingTitles:id,coach_id,title', 'media:id,coach_id,image', 'currentPosition:id,name'])
                    ->where('id', (int) $profileId)
                    ->where('status', 'approve')
                    ->first();

                if (! $coach) {
                    return $this->notFound([], 'Coach profile not found', 404);
                }

                $isOwner = (int) $coach->user_id === (int) $viewer->id;
                if (! $isOwner && $coach->privacy_settings !== 'public') {
                    return $this->forbidden([], 'This coach profile is private.', 403);
                }

                $coachProgramIds = ErProgram::query()
                    ->where('coach_id', $coach->id)
                    ->pluck('id');

                $programReviewsQuery = ErProgramReview::query()->whereIn('er_program_id', $coachProgramIds);
                $totalReviews = (int) $programReviewsQuery->count();
                $averageRating = $totalReviews > 0
                    ? round((float) $programReviewsQuery->avg('rating'), 1)
                    : 0.0;

                $data = [
                    'coach_id' => $coach->id,
                    'visibility' => $coach->status === 'approve' ? 'public' : 'pending',
                    'profile' => [
                        'name' => trim((string) ($coach->name ?? '') . ' ' . (string) ($coach->last_name ?? '')),
                        'sports' => $coach->sports,
                        'email' => $coach->email,
                        'nationality' => $coach->nationality,
                        'city_id' => $coach->city_id,
                        'country_id' => $coach->country_id,
                        'location' => $this->formatLocation($coach->city, $coach->country),
                        'profile_image' => $coach->coach_profile_pic ? asset($coach->coach_profile_pic) : null,
                        'bio' => $coach->coaching_philosophy,
                        'current_role' => $coach->currentPosition
                            ? ['id' => (int) $coach->currentPosition->id, 'name' => $coach->currentPosition->name]
                            : null,
                        'years_of_experience' => $coach->years_of_experience,
                        'highest_education' => $coach->highest_education,
                        'coaching_education' => $coach->coaching_education,
                        'player_centric_approach' => (bool) $coach->player_centric_approach,
                        'data_driving_training' => (bool) $coach->data_driving_training,
                    ],
                    'rating_summary' => [
                        'average_rating' => $averageRating,
                        'total_reviews' => $totalReviews,
                        'total_programs' => (int) $coachProgramIds->count(),
                    ],
                    'coaching_titles' => $coach->coachingTitles->pluck('title')->values(),
                    'coach_media' => $coach->media
                        ->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'image' => ! empty($item->image) ? asset($item->image) : null,
                            ];
                        })
                        ->values(),
                    'badges' => array_values(array_filter([
                        $coach->player_centric_approach ? 'Player-Centric Approach' : null,
                        $coach->data_driving_training ? 'Data-Driven Progress Tracking' : null,
                    ])),
                ];

                return $this->success($data, 'Coach profile fetched successfully', 200);
            }

            if ($profileType === 'club') {
                $club = ClubProfile::query()->with('user:id,name,last_name,profile_image')->find((int) $profileId);

                if (! $club) {
                    return $this->notFound([], 'Club profile not found', 404);
                }

                return $this->success([
                    'id' => $club->id,
                    'club_name' => $club->club_name,
                    'sports' => $club->sports,
                    'city_id' => $club->city_id,
                    'country_id' => $club->country_id,
                    'location' => $this->formatLocation($club->city, $club->country),
                    'club_description' => $club->club_description,
                    'club_logo' => ! empty($club->club_logo) ? asset($club->club_logo) : null,
                    'profile_image' => ! empty($club->user?->profile_image) ? asset($club->user->profile_image) : null,
                ], 'Club profile fetched successfully', 200);
            }

            return $this->validationError([
                'profile_type' => ['Invalid profile_type. Use player, coach, or club.'],
            ], 'Validation failed', 422);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }



    private function canViewAthleteProfile($viewer, AthleteProfiles $profile): bool
    {
        if (! $viewer) {
            return false;
        }

        $privacy = strtolower((string) ($profile->privacy_settings ?: 'public'));

        if ($privacy === 'public') {
            return true;
        }

        if ($privacy === 'only_player') {
            if (! in_array($viewer->role, ['parent', 'player'], true)) {
                return false;
            }

            $viewerParentId = $viewer->role === 'parent'
                ? $viewer->id
                : AthleteProfiles::query()->where('user_id', $viewer->id)->value('parent_id');

            return ! is_null($viewerParentId)
                && ! is_null($profile->parent_id)
                && (int) $viewerParentId === (int) $profile->parent_id;
        }

        if ($privacy === 'coach_and_team') {
            if ($viewer->role !== 'coach') {
                return false;
            }

            $viewerCoachClubIds = TeamPlayer::query()
                ->where('coach_id', $viewer->id)
                ->whereNotNull('club_id')
                ->pluck('club_id')
                ->unique()
                ->values()
                ->all();

            if (empty($viewerCoachClubIds)) {
                return false;
            }

            $targetClubIds = TeamPlayer::query()
                ->whereNotNull('club_id')
                ->where(function ($query) use ($profile) {
                    $query->where('child_id', $profile->id);

                    if (! is_null($profile->user_id)) {
                        $query->orWhere('player_id', $profile->user_id);
                    }
                })
                ->pluck('club_id')
                ->unique()
                ->values()
                ->all();

            if (empty($targetClubIds)) {
                return false;
            }

            return count(array_intersect($viewerCoachClubIds, $targetClubIds)) > 0;
        }

        return false;
    }
}
