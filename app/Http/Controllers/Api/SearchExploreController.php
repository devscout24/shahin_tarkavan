<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\ClubProfile;
use App\Models\ClubRecruitment;
use App\Models\Coach;
use App\Models\ErProgram;
use App\Models\ErProgramReview;
use App\Models\TeamPlayer;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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

        $filterMinPrice = is_numeric($rawMinPrice) ? (float) $rawMinPrice : null;
        $filterMaxPrice = is_numeric($rawMaxPrice) ? (float) $rawMaxPrice : null;

        if ($filterMinPrice !== null && $filterMaxPrice !== null && $filterMinPrice > $filterMaxPrice) {
            [$filterMinPrice, $filterMaxPrice] = [$filterMaxPrice, $filterMinPrice];
        }

        if ($user->role == "coach" || $user->role == "club") {
            $players = AthleteProfiles::query()
                ->whereIn('privacy_settings', ['public', 'coach_and_team'])
                ->when($viewerContext['country'], function (Builder $query) use ($viewerContext) {
                    $query->where('country', 'like', '%' . $viewerContext['country'] . '%');
                })
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
                        'age_group' => $this->resolveAgeGroup($this->resolveAge($profile->dob)),
                        'position' => $profile->primaryPosition?->name,
                        'sports' => $profile->sports,
                        'jersey_number' => $profile->jersey_number,
                        'location' => $this->formatLocation($profile->city, $profile->country),
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
                ->when($viewerContext['country'], function (Builder $query) use ($viewerContext) {
                    $query->where('country', 'like', '%' . $viewerContext['country'] . '%');
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
                ->when($viewerContext['country'], function (Builder $query) use ($viewerContext) {
                    $query->where('country', 'like', '%' . $viewerContext['country'] . '%');
                })
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
                        'age_group' => $this->resolveAgeGroup($this->resolveAge($profile->dob)),
                        'position' => $profile->primaryPosition?->name,
                        'sports' => $profile->sports,
                        'jersey_number' => $profile->jersey_number,
                        'location' => $this->formatLocation($profile->city, $profile->country),
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
                ->when($viewerContext['country'], function (Builder $query) use ($viewerContext) {
                    $query->where('country', 'like', '%' . $viewerContext['country'] . '%');
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
                        'coaching_philosophy' => $coach->coaching_philosophy,
                        'player_centric_approach' => (bool) $coach->player_centric_approach,
                        'data_driving_training' => (bool) $coach->data_driving_training,
                        'profile_image' => $this->resolveCoachImage($coach),
                    ];
                })
                ->values();
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
            ->when($viewerContext['country'] || $viewerContext['city'], function (Builder $query) use ($viewerContext) {
                $query->where(function (Builder $locationQuery) use ($viewerContext) {
                    if ($viewerContext['country']) {
                        $locationQuery->where('country', 'like', '%' . $viewerContext['country'] . '%');
                    }

                    if ($viewerContext['city']) {
                        if ($viewerContext['country']) {
                            $locationQuery->orWhere('city', 'like', '%' . $viewerContext['city'] . '%');
                        } else {
                            $locationQuery->where('city', 'like', '%' . $viewerContext['city'] . '%');
                        }
                    }
                });
            })
            ->with(['user:id,name,last_name,profile_image'])
            ->orderByDesc('id')
            ->get()
            ->map(function (ClubProfile $club) {
                return [
                    'type' => 'club',
                    'club_profile_id' => $club->id,
                    'club_id' => $club->user_id,
                    'club_name' => $club->club_name,
                    'sports_name' => $club->sports,
                    'location' => $this->formatLocation($club->city, $club->country),
                    'club_description' => $club->club_description,
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
                $query->where(function (Builder $ageQuery) use ($viewerContext) {
                    $ageQuery->whereNull('upto_age')
                        ->orWhere('upto_age', '>=', $viewerContext['age']);
                });
            })
            ->with(['coach:id,user_id,name,last_name,coach_profile_pic,city,country'])
            ->orderBy('program_start')
            ->get()
            ->map(function (ErProgram $program) {
                return [
                    'type' => 'program',
                    'program_id' => $program->id,
                    'coach_id' => $program->coach_id,
                    'program_name' => $program->program_name,
                    'sport' => $program->sport,
                    'program_price' => $program->program_price,
                    'age_group' => $this->resolveAgeGroup($program->upto_age),
                    'upto_age' => $program->upto_age,
                    'location' => $program->program_location,
                    'program_start' => optional($program->program_start)?->toDateString(),
                    'program_end' => optional($program->program_end)?->toDateString(),
                    'program_photo' => ! empty($program->program_photo) ? asset($program->program_photo) : null,
                    'coach_name' => trim((string) ($program->coach?->name ?? '') . ' ' . (string) ($program->coach?->last_name ?? '')),
                ];
            })
            ->values();

        $upcomingEvents = ClubRecruitment::query()
            ->when($recruitmentType, function (Builder $query) use ($recruitmentType) {
                $query->where('recruitment_type', $recruitmentType);
            })
            ->where('status', 'active')
            ->where(function (Builder $dateQuery) {
                $dateQuery->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', Carbon::now()->toDateString());
            })
            ->when($viewerContext['country'] || $viewerContext['city'], function (Builder $query) use ($viewerContext) {
                $query->whereHas('club.club', function (Builder $locationQuery) use ($viewerContext) {
                    if ($viewerContext['country']) {
                        $locationQuery->where('country', 'like', '%' . $viewerContext['country'] . '%');
                    }

                    if ($viewerContext['city']) {
                        if ($viewerContext['country']) {
                            $locationQuery->orWhere('city', 'like', '%' . $viewerContext['city'] . '%');
                        } else {
                            $locationQuery->where('city', 'like', '%' . $viewerContext['city'] . '%');
                        }
                    }
                });
            })
            ->when($viewerContext['age'] !== null, function (Builder $query) use ($viewerContext) {
                $query->where(function (Builder $ageQuery) use ($viewerContext) {
                    $ageQuery->whereNull('upto_age')
                        ->orWhere('upto_age', '>=', $viewerContext['age']);
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
                    'recruitment_id' => $event->id,
                    'club_id' => $event->club_id,
                    'club_team_id' => $event->club_team_id,
                    'team_name' => $event->clubTeam?->name,
                    'recruitment_type' => $event->recruitment_type,
                    'age_group' => $event->clubTeam?->age_group ?? $this->resolveAgeGroup($event->upto_age),
                    'upto_age' => $event->upto_age,
                    'end_date' => optional($event->end_date)?->toDateTimeString(),
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

        $allData = collect($players)
            ->merge($coaches)
            ->merge($clubs)
            ->merge($programs)
            ->merge($upcomingEvents);

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

        $rawPaginationNumber = $request->input('pagination_number')
            ?? $request->query('pagination_number')
            ?? $request->input('per_page')
            ?? $request->query('per_page')
            ?? null;

        $paginationNumber = is_numeric($rawPaginationNumber) && (int) $rawPaginationNumber > 0
            ? (int) $rawPaginationNumber
            : null;

        $rawPage = $request->input('page') ?? $request->query('page') ?? 1;
        $currentPage = is_numeric($rawPage) && (int) $rawPage > 0 ? (int) $rawPage : 1;

        $paginateCollection = function ($items) use ($paginationNumber, $currentPage): array {
            $collection = collect($items)->values();
            $total = $collection->count();

            if ($paginationNumber === null) {
                return [
                    'items' => $collection,
                    'meta' => null,
                ];
            }

            $offset = ($currentPage - 1) * $paginationNumber;
            $pagedItems = $collection->slice($offset, $paginationNumber)->values();
            $lastPage = (int) max(1, ceil($total / $paginationNumber));

            return [
                'items' => $pagedItems,
                'meta' => [
                    'current_page' => $currentPage,
                    'per_page' => $paginationNumber,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'has_more' => $currentPage < $lastPage,
                ],
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

        // Apply sports filter if provided
        if ($filterSports) {
            $sportsList = array_values(array_filter(array_map('trim', explode(',', $filterSports))));

            $matchesSports = function (?string $value) use ($sportsList): bool {
                $value = strtolower(trim((string) $value));
                if ($value === '' || empty($sportsList)) {
                    return false;
                }

                foreach ($sportsList as $sport) {
                    $sport = strtolower(trim((string) $sport));
                    if ($sport !== '' && strpos($value, $sport) !== false) {
                        return true;
                    }
                }

                return false;
            };

            $players = collect($players)->filter(function ($item) use ($matchesSports) {
                return $matchesSports($item['sports'] ?? null);
            })->values()->all();

            $coaches = collect($coaches)->filter(function ($item) use ($matchesSports) {
                return $matchesSports($item['sports'] ?? null);
            })->values()->all();

            $clubs = collect($clubs)->filter(function ($item) use ($matchesSports) {
                return $matchesSports($item['sports_name'] ?? null);
            })->values()->all();

            $programs = collect($programs)->filter(function ($item) use ($matchesSports) {
                return $matchesSports($item['sport'] ?? null);
            })->values()->all();

            $upcomingEvents = collect($upcomingEvents)->filter(function ($item) use ($matchesSports) {
                return $matchesSports($item['sports'] ?? null);
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
                    'program_name',
                    'sport',
                    'location',
                    'coach_name',
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
            $normalizeAgeGroup = function (?string $value): ?string {
                $value = strtoupper(trim((string) $value));
                return $value !== '' ? preg_replace('/\s+/', '', $value) : null;
            };

            $resolveAgeGroupFromAge = function (?int $age) use ($normalizeAgeGroup): ?string {
                if ($age === null) {
                    return null;
                }

                if ($age <= 5) {
                    return $normalizeAgeGroup('U4/U5');
                }

                if ($age <= 7) {
                    return $normalizeAgeGroup('U6/U7');
                }

                if ($age <= 8) {
                    return $normalizeAgeGroup('U' . $age);
                }

                if ($age <= 9) {
                    return $normalizeAgeGroup('U' . $age);
                }

                if ($age <= 10) {
                    return $normalizeAgeGroup('U' . $age);
                }

                if ($age <= 11) {
                    return $normalizeAgeGroup('U' . $age);
                }

                if ($age <= 12) {
                    return $normalizeAgeGroup('U' . $age);
                }


                if ($age <= 13) {
                    return $normalizeAgeGroup('U' . $age);
                }


                if ($age <= 14) {
                    return $normalizeAgeGroup('U' . $age);
                }

                if ($age <= 15) {
                    return $normalizeAgeGroup('U' . $age);
                }

                if ($age <= 16) {
                    return $normalizeAgeGroup('U' . $age);
                }

                if ($age <= 17) {
                    return $normalizeAgeGroup('U' . $age);
                }

                if ($age <= 18) {
                    return $normalizeAgeGroup('U' . $age);
                }

                return $normalizeAgeGroup('Senior Team');
            };

            $canonicalRequestedAgeGroup = function (string $value) use ($normalizeAgeGroup, $resolveAgeGroupFromAge): ?string {
                $normalized = $normalizeAgeGroup($value);

                if (! $normalized) {
                    return null;
                }

                if (preg_match('/^\d+$/', $normalized)) {
                    return $resolveAgeGroupFromAge((int) $normalized);
                }

                if (strpos($normalized, 'SENIOR') !== false) {
                    return $normalizeAgeGroup('Senior Team');
                }

                if (preg_match('/U\d+/i', $normalized, $match)) {
                    return $normalizeAgeGroup($match[0]);
                }

                return $normalized;
            };

            $extractComparableAgeGroup = function (array $item) use ($normalizeAgeGroup, $resolveAgeGroupFromAge): ?string {
                $rawAgeGroup = $normalizeAgeGroup($item['age_group'] ?? null);

                if ($rawAgeGroup) {
                    if (strpos($rawAgeGroup, 'SENIOR') !== false) {
                        return $normalizeAgeGroup('Senior Team');
                    }

                    if (preg_match('/U\d+/i', $rawAgeGroup, $match)) {
                        return $normalizeAgeGroup($match[0]);
                    }

                    return $rawAgeGroup;
                }

                $uptoAge = isset($item['upto_age']) ? (int) $item['upto_age'] : null;
                return $resolveAgeGroupFromAge($uptoAge);
            };

            $targetAgeGroup = $canonicalRequestedAgeGroup($filterAgeGroup);

            $matchesAgeGroup = function ($item) use ($extractComparableAgeGroup, $targetAgeGroup): bool {
                $itemAgeGroup = $extractComparableAgeGroup((array) $item);
                return $itemAgeGroup !== null && $targetAgeGroup !== null && $itemAgeGroup === $targetAgeGroup;
            };

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

            $paginatedSection = $paginateCollection($sectionItems);
            $sectionItems = $paginatedSection['items'];

            return $this->success([
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
                $resolvedSection => $sectionItems->values(),
                'data' => $sectionItems->values(),
                'total' => $sectionItems->count(),
                'pagination' => $paginatedSection['meta'],
                'age_group_counts' => [
                    $resolvedSection => $this->buildLabelCounts($sectionItems, 'age_group'),
                ],
            ], 'Data fetched successfully', 200);
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
                'sports' => $filterSports ?: null,
                'age_group' => $filterAgeGroup ?: null,
                'min_price' => $filterMinPrice,
                'max_price' => $filterMaxPrice,
                'pagination_number' => $paginationNumber,
                'page' => $currentPage,
            ],
            'data' => $allDataItems->values(),
            'players' => collect($players)->values(),
            'coaches' => collect($coaches)->values(),
            'clubs' => $clubs,
            'programs' => $programs,
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
        $age = null;

        if ($user->role === 'player') {
            $profile = AthleteProfiles::query()->where('user_id', $user->id)->latest('id')->first();
            $city = $profile?->city;
            $country = $profile?->country;
            $age = $this->resolveAge($profile?->dob);
        } elseif ($user->role === 'coach') {
            $profile = Coach::query()->where('user_id', $user->id)->latest('id')->first();
            $city = $profile?->city;
            $country = $profile?->country;
            $age = $this->resolveAge($profile?->dob);
        } elseif ($user->role === 'parent') {
            $profile = AthleteProfiles::query()->where('parent_id', $user->id)->latest('id')->first();
            $city = $profile?->city;
            $country = $profile?->country;
            $age = $this->resolveAge($profile?->dob);
        }

        if ($age === null) {
            $age = $this->resolveAge($user->dob ?? null);
        }

        $city = $this->normalizeLocationValue($city);
        $country = $this->normalizeLocationValue($country);

        return [
            'city' => $city,
            'country' => $country,
            'location' => $city ?: $country,
            'age' => $age,
            'age_group' => $this->resolveAgeGroup($age),
        ];
    }

    private function resolveAgeGroup(?int $age): ?string
    {
        if ($age === null) {
            return null;
        }

        if ($age <= 5) {
            return 'U4/U5';
        }

        if ($age <= 7) {
            return 'U6/U7';
        }

        if ($age <= 18) {
            return 'U' . $age;
        }

        return 'Senior Team';
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
