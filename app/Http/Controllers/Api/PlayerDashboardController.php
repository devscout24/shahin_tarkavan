<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\ErProgram;
use App\Models\ProgramBooking;
use App\Models\RecruitementApply;
use App\Support\AgeGroup;
use App\Traits\ProgramProviderTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlayerDashboardController extends Controller
{
    use ProgramProviderTrait;

    public function playerDashboard(Request $request)
    {
        return $this->upcomingEvents($request);
    }

    private function getAgeRange(int $age): array
    {
        if ($age <= 8) {
            return [0, 8];
        }
        if ($age <= 12) {
            return [9, 12];
        }
        if ($age <= 17) {
            return [13, 17];
        }
        if ($age <= 21) {
            return [18, 21];
        }
        if ($age <= 30) {
            return [22, 30];
        }
        return [31, 999];
    }

    public function listAvailablePrograms(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            $sportsName = trim((string) $request->query('sports', $request->input('sports_name', '')));

            if (! $user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Authentication required.',
                    'errors' => [],
                ], 401);
            }

            $profile = null;
            $age = null;
            $allChildAges = [];

            if ($user->role === 'coach') {
                // Coach sees everything, no age filter
            } else {
                $activeChildId = $request->header('active-child-id');

                if ($user->role === 'parent') {
                    // Get ages of all children for the parent
                    $allChildAges = AthleteProfiles::query()
                        ->where('parent_id', $user->id)
                        ->whereNotNull('dob')
                        ->get()
                        ->map(function ($p) {
                            return Carbon::parse($p->dob)->age;
                        })
                        ->unique()
                        ->values()
                        ->toArray();

                    // Still need a profile to determine city
                    $profile = AthleteProfiles::query()
                        ->when($activeChildId, function ($q) use ($activeChildId, $user) {
                            return $q->where('id', $activeChildId)->where('parent_id', $user->id);
                        })
                        ->when(!$activeChildId, function ($q) use ($user) {
                            return $q->where('parent_id', $user->id);
                        })
                        ->latest('id')
                        ->first();
                } elseif ($user->role === 'player') {
                    $profile = AthleteProfiles::query()
                        ->where('user_id', $user->id)
                        ->first();

                    if ($profile && $profile->dob) {
                        $age = Carbon::parse($profile->dob)->age;
                        $allChildAges = [$age];
                    }
                }

                if (!$profile && $user->role !== 'coach') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Athlete profile not found.',
                        'errors' => [],
                    ], 404);
                }
            }

            $athleteCityName = $profile ? trim((string) ($profile->city ?? '')) : '';

            $programQuery = ErProgram::query()
                ->with(['coach', 'times', 'goals', 'sportOption:id,name', 'user.club'])
                ->where('status', 'active')
                ->where('program_end', '>=', Carbon::now()->toDateString());

            // Filter by Location (City)
            if ($athleteCityName !== '') {
                $programQuery->where(function ($query) use ($athleteCityName) {
                    // Match with Coach's city (via coach relationship on ErProgram)
                    $query->whereHas('coach', function ($q) use ($athleteCityName) {
                        $q->where('city', 'like', '%' . $athleteCityName . '%');
                    })
                        // OR Match with Club's city (via user.club relationship)
                        ->orWhereHas('user.club', function ($q) use ($athleteCityName) {
                            $q->where('city', 'like', '%' . $athleteCityName . '%');
                        })
                        // OR Match with User's coach profile directly (via user.coachProfile relationship)
                        ->orWhereHas('user.coachProfile', function ($cq) use ($athleteCityName) {
                            $cq->where('city', 'like', '%' . $athleteCityName . '%');
                        });
                });
            }

            // Filter by Age (upto_age should be within the range of at least one child's age group)
            if ($user->role !== 'coach' && !empty($allChildAges)) {
                $programQuery->where(function ($query) use ($allChildAges) {
                    $query->whereNotNull('upto_age')
                        ->where(function ($q) use ($allChildAges) {
                            foreach ($allChildAges as $childAge) {
                                $range = $this->getAgeRange((int)$childAge);
                                $q->orWhere(function ($subQ) use ($range) {
                                    $subQ->whereRaw('CAST(upto_age AS UNSIGNED) >= ?', [$range[0]])
                                        ->whereRaw('CAST(upto_age AS UNSIGNED) <= ?', [$range[1]]);
                                });
                            }
                        });
                });
            }

            if ($sportsName !== '') {
                $programQuery->where('sport', 'like', '%' . $sportsName . '%');
            }

            $programs = $programQuery->orderBy('created_at', 'desc')->get()->map(function (ErProgram $program) {
                return $this->formatProgramData($program);
            });

            return response()->json([
                'status' => true,
                'message' => 'Available programs fetched successfully',
                'errors' => [],
                'data' => [
                    'viewer' => [
                        'age' => $age,
                        'city' => $athleteCityName,
                    ],
                    'filters' => [
                        'sports_name' => $sportsName,
                    ],
                    'programs' => $programs,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 500);
        }
    }



    public function upcomingEvents(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Authentication required.',
                    'errors' => [],
                ], 401);
            }

            $upcomingEvents = $this->getUpcomingEventsForUser($user);
            $topUpcoming = !empty($upcomingEvents) ? $upcomingEvents[0] : null;

            return response()->json([
                'status' => true,
                'message' => 'Upcoming events fetched successfully',
                'errors' => [],
                'data' => [
                    'top_upcoming' => $topUpcoming,
                    'upcoming_events' => $upcomingEvents,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 500);
        }
    }

    private function getUpcomingEventsForUser($user)
    {
        $today = now()->toDateString();

        // find athlete profile id for the user or their children
        $athleteIds = AthleteProfiles::query()
            ->when($user->role === 'player', function ($q) use ($user) {
                return $q->where('user_id', $user->id);
            })
            ->when($user->role === 'parent', function ($q) use ($user) {
                return $q->where('parent_id', $user->id);
            })
            ->pluck('id');

        if ($athleteIds->isEmpty()) {
            return [];
        }

        // 1. Fetch booked programs (Paid & Not Expired)
        $bookings = ProgramBooking::query()
            ->with([
                'program:id,program_name,program_start,program_end,program_location,program_photo,user_id',
                'program.user:id,name,last_name,profile_image',
                'program.user.club:id,user_id,club_name,club_logo',
                'bookingTime:id,time',
                'athlete:id,name,last_name'
            ])
            ->whereIn('athlete_profile_id', $athleteIds)
            ->where('payment_status', 'paid')
            ->where('status', 'confirmed')
            ->whereHas('program', function ($query) use ($today) {
                $query->whereDate('program_end', '>=', $today);
            })
            ->get()
            ->map(function ($booking) use ($today) {
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
                    'event_type' => 'program',
                    'booking_id' => $booking->id,
                    'athlete_name' => $booking->athlete?->name . ' ' . $booking->athlete?->last_name,
                    'provider_name' => $providerName,
                    'provider_image' => $providerImage,
                    'status' => $status,
                    'title' => $booking->program?->program_name,
                    'location' => $booking->program?->program_location,
                    'start_date' => $programStart ? Carbon::parse($programStart)->format('Y-m-d') : null,
                    'start_date_display' => $programStart ? Carbon::parse($programStart)->format('l, h:i A') : null,
                    'end_date' => $programEnd ? Carbon::parse($programEnd)->format('Y-m-d') : null,
                    'photo' => ! empty($booking->program?->program_photo) ? asset($booking->program->program_photo) : null,
                    'session_time' => $booking->bookingTime?->time,
                ];
            });

        // 2. Fetch Recruitment Applications (Not Expired)
        $recruitments = RecruitementApply::query()
            ->with([
                'recruitment:id,title,start_date,end_date,location,image,club_id',
                'recruitment.club:id,user_id,club_name,club_logo',
                'recruitment.club.user:id,profile_image',
                'child:id,name,last_name'
            ])
            ->whereIn('child_id', $athleteIds)
            ->whereHas('recruitment', function ($query) use ($today) {
                $query->whereDate('end_date', '>=', $today);
            })
            ->get()
            ->map(function ($apply) use ($today) {
                $startDate = $apply->recruitment?->start_date;
                $endDate = $apply->recruitment?->end_date;

                $status = 'Applied';
                if ($startDate && $endDate) {
                    $start = Carbon::parse($startDate);
                    $end = Carbon::parse($endDate);
                    $now = Carbon::parse($today);

                    if ($now->between($start, $end)) {
                        $status = 'In Progress';
                    }
                }

                // Identify Provider (Club for Recruitment)
                $providerName = 'Unknown Club';
                $providerImage = null;

                if ($apply->recruitment?->club) {
                    $club = $apply->recruitment->club;
                    $providerName = $club->club_name;
                    $providerImage = $club->club_logo ? asset($club->club_logo) : ($club->user ? asset($club->user->profile_image) : null);
                }

                return [
                    'event_type' => 'recruitment',
                    'apply_id' => $apply->id,
                    'athlete_name' => $apply->child?->name . ' ' . $apply->child?->last_name,
                    'provider_name' => $providerName,
                    'provider_image' => $providerImage,
                    'status' => $status,
                    'title' => $apply->recruitment?->title,
                    'location' => $apply->recruitment?->location,
                    'start_date' => $startDate ? Carbon::parse($startDate)->format('Y-m-d') : null,
                    'start_date_display' => $startDate ? Carbon::parse($startDate)->format('l, d M Y') : null,
                    'end_date' => $endDate ? Carbon::parse($endDate)->format('Y-m-d') : null,
                    'photo' => ! empty($apply->recruitment?->image) ? asset($apply->recruitment->image) : null,
                    'session_time' => null,
                ];
            });

        // Merge and sort by start_date
        return $bookings->concat($recruitments)
            ->sortBy('start_date')
            ->values()
            ->all();
    }
}
