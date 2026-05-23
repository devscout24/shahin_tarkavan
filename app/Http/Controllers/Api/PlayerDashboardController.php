<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\ClubRecruitment;
use App\Models\City;
use App\Models\Country;
use App\Models\ProgramBooking;
use App\Models\PlayerMediaVideo;
use App\Models\RecruitementApply;
use App\Support\AgeGroup;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlayerDashboardController extends Controller
{
    use ApiResponse;

    private function calculateProfileVisibility(?AthleteProfiles $athlete): int
    {
        if (! $athlete) {
            return 0;
        }

        $maxScore = 100;
        $score = 0;
        $fieldsCount = 0;

        // Basic Info
        if ($athlete->name && $athlete->last_name) {
            $score += 10;
        }
        $fieldsCount += 1;

        // Photo
        if ($athlete->image) {
            $score += 15;
        }
        $fieldsCount += 1;

        // Position
        if ($athlete->primary_position) {
            $score += 10;
        }
        $fieldsCount += 1;

        // Stats
        if ($athlete->total_played_games || $athlete->total_played_time || $athlete->goals || $athlete->assist) {
            $score += 20;
        }
        $fieldsCount += 1;

        // Bio
        if ($athlete->athlete_biography) {
            $score += 15;
        }
        $fieldsCount += 1;

        // Videos
        if ($athlete->mediaReels()->exists()) {
            $score += 15;
        }
        $fieldsCount += 1;

        // Strength/Skills
        if ($athlete->strengths()->exists()) {
            $score += 15;
        }
        $fieldsCount += 1;

        return min($score, $maxScore);
    }

    private function resolveLocationPayload(?int $cityId, ?int $countryId, ?string $cityName, ?string $countryName): array
    {
        $resolvedCityId = $cityId ?: ($cityName ? City::query()->whereRaw('LOWER(name) = ?', [strtolower(trim($cityName))])->value('id') : null);
        $resolvedCountryId = $countryId ?: ($countryName ? Country::query()->whereRaw('LOWER(name) = ?', [strtolower(trim($countryName))])->value('id') : null);

        return [
            'city_id' => $resolvedCityId ? (int) $resolvedCityId : null,
            'city' => $cityName,
            'country_id' => $resolvedCountryId ? (int) $resolvedCountryId : null,
            'country' => $countryName,
        ];
    }

    public function playerDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return $this->errors([], 'Authentication required.', 401);
            }

            // হেডার (active-child-id) অথবা রিকোয়েস্ট বডি/প্যারামস (active_child_id) উভয় থেকেই আইডি চেক করা হচ্ছে
            $activeChildId = $request->header('active-child-id') ?? $request->get('active_child_id');

            if ($user->role !== 'player' && $user->role !== 'parent' && !$activeChildId) {
                return $this->errors([], 'Only player or parent with active child can view this dashboard.', 403);
            }

            if ($user->role === 'parent' && !$activeChildId) {
                return $this->errors([], 'Please provide an active child profile ID.', 400);
            }

            // Get player profile
            $athlete = AthleteProfiles::query()
                ->when($activeChildId, function ($query) use ($activeChildId) {
                    return $query->where('id', $activeChildId);
                })
                ->when(!$activeChildId, function ($query) use ($user) {
                    return $query->where('user_id', $user->id);
                })
                ->with(['strengths', 'mediaReels', 'parentAggrement', 'primaryPosition:id,name', 'secondaryPosition:id,name'])
                ->first();

            if (! $athlete) {
                return $this->errors([], 'Player profile not found.', 404);
            }

            // 1. Profile Visibility Score
            $profileVisibility = $this->calculateProfileVisibility($athlete);

            // 2. Total Programs (all program bookings)
            $totalPrograms = ProgramBooking::query()
                ->where('athlete_profile_id', $athlete->id)
                ->count();

            // 3. Upcoming Sessions
            $today = now()->toDateString();
            $upcomingSessions = ProgramBooking::query()
                ->with(['program:id,program_name,program_start,program_end,program_location'])
                ->where('athlete_profile_id', $athlete->id)
                ->where('status', '!=', 'cancelled')
                ->whereHas('program', function ($query) use ($today) {
                    $query->whereDate('program_start', '>=', $today);
                })
                ->count();

            // 4. Recent Payments (sum of recent paid programs)
            $recentPayments = ProgramBooking::query()
                ->where('athlete_profile_id', $athlete->id)
                ->where('payment_status', 'paid')
                ->whereDate('created_at', '>=', now()->subMonths(3)->toDateString())
                ->sum('amount');

            // 5. Videos Uploaded
            $videosUploaded = PlayerMediaVideo::query()
                ->where('player_profile_id', $athlete->id)
                ->whereIn('status', ['reels', 'youtube'])
                ->count();

            // 6. Recent Opportunities (recruitment matching player's position/age)
            $playerAge = null;
            if ($athlete->dob) {
                try {
                    $playerAge = Carbon::parse($athlete->dob)->age;
                } catch (\Throwable) {
                    // Handle invalid date
                }
            }

            $opportunities = collect();

            if ($playerAge !== null) {
                $opportunities = ClubRecruitment::query()
                    ->with([
                        'club:id,name,last_name,email',
                        'club.club:id,user_id,club_name,club_logo,city,state,country,city_id,country_id',
                        'clubTeam:id,name,age_group,image,competition_level_id',
                        'clubTeam.competitionLevel:id,name',
                        'playerPosition:id,name',
                    ])
                    ->where('status', 'active')
                    ->where('recruitment_type', 'player')
                    // Filter recruitments by the player's location (city or country) when available
                    ->when($athlete->city_id || $athlete->country_id, function ($query) use ($athlete) {
                        return $query->whereHas('club.club', function ($q) use ($athlete) {
                            if ($athlete->city_id) {
                                $q->where('city_id', $athlete->city_id);
                            }

                            if ($athlete->country_id) {
                                $q->orWhere('country_id', $athlete->country_id);
                            }
                        });
                    })
                    ->where(function ($dateQuery) use ($today) {
                        $dateQuery->where(function ($q) use ($today) {
                            $q->whereNull('start_date')
                                ->orWhereDate('start_date', '<=', $today);
                        })
                            ->where(function ($q) use ($today) {
                                $q->whereNull('end_date')
                                    ->orWhereDate('end_date', '>=', $today);
                            });
                    })
                    ->whereNotNull('upto_age')
                    ->where('upto_age', '>=', $playerAge)
                    ->when($athlete->primary_position, function ($query) use ($athlete) {
                        return $query->where('player_position', $athlete->primary_position);
                    })
                    ->orderBy('end_date')
                    ->limit(5)
                    ->get()
                    ->map(function (ClubRecruitment $recruitment) use ($user, $athlete) {
                        $competitionLevel = $recruitment->clubTeam?->competitionLevel?->name;
                        $ageGroup = $recruitment->clubTeam?->age_group;
                        $formattedAge = [];
                        if ($competitionLevel) {
                            $formattedAge[] = $competitionLevel;
                        }
                        if ($ageGroup) {
                            $formattedAge[] = "Age: {$ageGroup}";
                        }

                        $application = RecruitementApply::query()
                            ->where('recruitment_id', $recruitment->id)
                            ->where(function ($q) use ($user, $athlete) {
                                $q->where('user_id', $user->id);
                                if ($athlete && $athlete->id) {
                                    $q->orWhere('child_id', $athlete->id);
                                }
                            })
                            ->first();

                        return [
                            'id' => $recruitment->id,
                            'club' => [
                                'id' => $recruitment->club?->club?->id,
                                'club_name' => $recruitment->club?->club?->club_name,
                                'club_logo' => ! empty($recruitment->club?->club?->club_logo) ? asset($recruitment->club->club->club_logo) : null,
                                'city' => $recruitment->club?->club?->city,
                                'state' => $recruitment->club?->club?->state,
                                'country' => $recruitment->club?->club?->country,
                                'city_id' => $recruitment->club?->club?->city_id ? (int) $recruitment->club->club->city_id : null,
                                'country_id' => $recruitment->club?->club?->country_id ? (int) $recruitment->club->club->country_id : null,
                            ],
                            'position' => [
                                'id' => $recruitment->player_position ? (int) $recruitment->player_position : null,
                                'name' => $recruitment->playerPosition?->name,
                            ],
                            'team' => [
                                'id' => $recruitment->clubTeam?->id,
                                'name' => $recruitment->clubTeam?->name,
                                'age_group' => AgeGroup::normalize($recruitment->clubTeam?->age_group),
                                'image' => ! empty($recruitment->clubTeam?->image) ? asset($recruitment->clubTeam->image) : null,
                                'competition_level' => $recruitment->clubTeam?->competitionLevel?->name,
                                'formatted_age' => implode(' | ', $formattedAge) ?: null,
                            ],
                            'experience' => $recruitment->experience,
                            'description' => $recruitment->description,
                            'upto_age' => $recruitment->upto_age,
                            'start_date' => $recruitment->start_date
                                ? ($recruitment->start_date instanceof CarbonInterface
                                    ? $recruitment->start_date->toDateString()
                                    : Carbon::parse($recruitment->start_date)->toDateString())
                                : null,
                            'tryout_date' => $recruitment->end_date
                                ? ($recruitment->end_date instanceof CarbonInterface
                                    ? $recruitment->end_date->format('F d-j, Y')
                                    : Carbon::parse($recruitment->end_date)->format('F d-j, Y'))
                                : null,
                            'end_date' => $recruitment->end_date
                                ? ($recruitment->end_date instanceof CarbonInterface
                                    ? $recruitment->end_date->toDateString()
                                    : Carbon::parse($recruitment->end_date)->toDateString())
                                : null,
                            'application_status' => $application ? 'applied' : null,
                        ];
                    })
                    ->values();
            }

            // 7. Upcoming Training Sessions (program reminders)
            $upcomingTraining = ProgramBooking::query()
                ->with([
                    'program:id,program_name,program_start,program_end,program_location,program_photo,user_id',
                    'program.user:id,name,last_name,profile_image',
                    'program.user.club:id,user_id,club_name,club_logo',
                    'bookingTime:id,time',
                ])
                ->where('athlete_profile_id', $athlete->id)
                ->where('status', '!=', 'cancelled')
                ->whereHas('program', function ($query) use ($today) {
                    $query->whereDate('program_end', '>=', $today);
                })
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function (ProgramBooking $booking) use ($today) {
                    $programStart = $booking->program?->program_start;
                    $programEnd = $booking->program?->program_end;

                    $status = 'Upcoming';
                    if ($programStart && $programEnd) {
                        $start = Carbon::parse($programStart);
                        $end = Carbon::parse($programEnd);
                        $now = Carbon::parse($today);

                        if ($now->between($start, $end)) {
                            $status = 'In Progress';
                        } elseif ($now->gt($end)) {
                            $status = 'Completed';
                        }
                    }

                    // Identify Provider (Club or Coach)
                    $providerName = 'Unknown Provider';
                    $providerImage = null;

                    if ($booking->program?->user) {
                        $user = $booking->program->user;
                        if ($user->club) {
                            $providerName = $user->club->club_name;
                            $providerImage = $user->club->club_logo ? asset($user->club->club_logo) : ($user->profile_image ? asset($user->profile_image) : null);
                        } else {
                            $providerName = $user->name . ' ' . $user->last_name;
                            $providerImage = $user->profile_image ? asset($user->profile_image) : null;
                        }
                    }

                    return [
                        'booking_id' => $booking->id,
                        'status' => $status,
                        'provider_name' => $providerName,
                        'provider_image' => $providerImage,
                        'program' => [
                            'id' => $booking->program?->id,
                            'name' => $booking->program?->program_name,
                            'location' => $booking->program?->program_location,
                            'start_date' => $programStart ? Carbon::parse($programStart)->format('l, h:i A') : null,
                            'start_date_full' => $programStart ? Carbon::parse($programStart)->toDateString() : null,
                            'end_date' => $programEnd ? Carbon::parse($programEnd)->toDateString() : null,
                            'photo' => ! empty($booking->program?->program_photo) ? asset($booking->program->program_photo) : null,
                        ],
                        'session_time' => $booking->bookingTime?->time,
                    ];
                })
                ->values();

            // 8. Scouting Status (basic view of endorse/recommendations)
            $scoutingStatus = [
                'profile_completeness' => $profileVisibility,
                'total_recruitments_applied' => 0, // Can be added if tracking apply history
                'scouts_viewing' => 0, // Can be added if tracking viewed history
            ];

            return $this->success([
                'player_info' => [
                    'id' => $athlete->id,
                    'name' => trim((string) $athlete->name . ' ' . (string) $athlete->last_name),
                    'image' => ! empty($athlete->image) ? asset($athlete->image) : null,
                    'position' => [
                        'id' => $athlete->primary_position ? (int) $athlete->primary_position : null,
                        'name' => $athlete->primaryPosition?->name,
                    ],
                    'secondary_position' => [
                        'id' => $athlete->secondary_position ? (int) $athlete->secondary_position : null,
                        'name' => $athlete->secondaryPosition?->name,
                    ],
                    'age' => $playerAge,
                    'jersey_number' => $athlete->jersey_number,
                    'city' => $athlete->city,
                    'country' => $athlete->country,
                    'city_id' => $athlete->city_id ? (int) $athlete->city_id : null,
                    'country_id' => $athlete->country_id ? (int) $athlete->country_id : null,
                    'location' => $this->resolveLocationPayload($athlete->city_id, $athlete->country_id, $athlete->city, $athlete->country),
                    'privacy_setting' => $athlete->privacy_setting,
                ],
                'summary' => [
                    'profile_visibility' => $athlete->privacy_setting,
                    'total_programs' => $totalPrograms,
                    'upcoming_sessions' => $upcomingSessions,
                    'recent_payments' => round((float) $recentPayments, 2),
                    'videos_uploaded' => $videosUploaded,
                ],
                'scouting_status' => $scoutingStatus,
                'recent_opportunities' => $opportunities,
                'upcoming_training' => $upcomingTraining,

            ], 'Player dashboard data fetched successfully.', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }
}
