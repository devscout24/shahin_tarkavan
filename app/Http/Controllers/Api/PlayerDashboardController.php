<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\ClubRecruitment;
use App\Models\ProgramBooking;
use App\Models\PlayerMediaVideo;
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
        if ($athlete->total_played_games || $athlete->goals || $athlete->assist) {
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

    public function playerDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return $this->errors([], 'Authentication required.', 401);
            }

            if ($user->role !== 'player') {
                return $this->errors([], 'Only player can view this dashboard.', 403);
            }

            // Get player profile
            $athlete = AthleteProfiles::query()
                ->where('user_id', $user->id)
                ->with(['strengths', 'mediaReels', 'parentAggrement'])
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
                ->where('status', ['reels', 'youtube'])
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
                        'club.club:id,user_id,club_name,club_logo,city,state,country',
                        'clubTeam:id,name,age_group,image,competition_level_id',
                        'clubTeam.competitionLevel:id,name',
                        'playerPosition:id,name',
                    ])
                    ->where('status', 'active')
                    ->where('recruitment_type', 'player')
                    ->whereDate('end_date', '>=', $today)
                    ->whereNotNull('upto_age')
                    ->where('upto_age', '>=', $playerAge)
                    ->when($athlete->primary_position, function ($query) use ($athlete) {
                        return $query->where('player_position', $athlete->primary_position);
                    })
                    ->orderBy('end_date')
                    ->limit(5)
                    ->get()
                    ->map(function (ClubRecruitment $recruitment) {
                        $competitionLevel = $recruitment->clubTeam?->competitionLevel?->name;
                        $ageGroup = $recruitment->clubTeam?->age_group;
                        $formattedAge = [];
                        if ($competitionLevel) {
                            $formattedAge[] = $competitionLevel;
                        }
                        if ($ageGroup) {
                            $formattedAge[] = "Age: {$ageGroup}";
                        }

                        return [
                            'id' => $recruitment->id,
                            'club' => [
                                'id' => $recruitment->club?->club?->id,
                                'club_name' => $recruitment->club?->club?->club_name,
                                'club_logo' => ! empty($recruitment->club?->club?->club_logo) ? asset($recruitment->club->club->club_logo) : null,
                                'city' => $recruitment->club?->club?->city,
                                'state' => $recruitment->club?->club?->state,
                                'country' => $recruitment->club?->club?->country,
                            ],
                            'position' => $recruitment->playerPosition?->name,
                            'team' => [
                                'id' => $recruitment->clubTeam?->id,
                                'name' => $recruitment->clubTeam?->name,
                                'age_group' => $recruitment->clubTeam?->age_group,
                                'image' => ! empty($recruitment->clubTeam?->image) ? asset($recruitment->clubTeam->image) : null,
                                'competition_level' => $recruitment->clubTeam?->competitionLevel?->name,
                                'formatted_age' => implode(' | ', $formattedAge) ?: null,
                            ],
                            'experience' => $recruitment->experience,
                            'description' => $recruitment->description,
                            'upto_age' => $recruitment->upto_age,
                            'tryout_date' => $recruitment->end_date
                                ? ($recruitment->end_date instanceof CarbonInterface
                                    ? $recruitment->end_date->format('F d-j, Y')
                                    : Carbon::parse($recruitment->end_date)->format('F d-j, Y'))
                                : null,
                        ];
                    })
                    ->values();
            }

            // 7. Upcoming Training Sessions (program reminders)
            $upcomingTraining = ProgramBooking::query()
                ->with([
                    'program:id,program_name,program_start,program_end,program_location,program_photo',
                    'bookingTime:id,time',
                ])
                ->where('athlete_profile_id', $athlete->id)
                ->where('status', '!=', 'cancelled')
                ->whereHas('program', function ($query) use ($today) {
                    $query->whereDate('program_start', '>=', $today);
                })
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function (ProgramBooking $booking) {
                    return [
                        'booking_id' => $booking->id,
                        'program' => [
                            'id' => $booking->program?->id,
                            'name' => $booking->program?->program_name,
                            'location' => $booking->program?->program_location,
                            'start_date' => optional($booking->program?->program_start)->format('l, h:i A'),
                            'start_date_full' => optional($booking->program?->program_start)->toDateString(),
                            'end_date' => optional($booking->program?->program_end)->toDateString(),
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
                    'position' => $athlete->primary_position,
                    'age' => $playerAge,
                    'jersey_number' => $athlete->jersey_number,
                    'city' => $athlete->city,
                    'country' => $athlete->country,
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
