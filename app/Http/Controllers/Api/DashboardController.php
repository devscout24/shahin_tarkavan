<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\ClubRecruitment;
use App\Models\ProgramBooking;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    use ApiResponse;

    private function ageFromDob(?string $dob): ?int
    {
        if (! $dob) {
            return null;
        }

        try {
            return Carbon::parse($dob)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    public function parentDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return $this->errors([], 'Authentication required.', 401);
            }

            if ($user->role !== 'parent') {
                return $this->errors([], 'Only parent can view this dashboard.', 403);
            }

            $children = AthleteProfiles::query()
                ->where('parent_id', $user->id)
                ->select(['id', 'name', 'last_name', 'dob'])
                ->get()
                ->map(function (AthleteProfiles $child) {
                    $age = $this->ageFromDob($child->dob);

                    return [
                        'id' => $child->id,
                        'name' => trim((string) $child->name . ' ' . (string) $child->last_name),
                        'age' => $age,
                    ];
                })
                ->values();

            $childAges = $children
                ->pluck('age')
                ->filter(fn($age) => is_int($age) && $age > 0)
                ->values();

            $today = now()->toDateString();

            $upcomingRecruitments = collect();

            if ($childAges->isNotEmpty()) {

                $upcomingRecruitments = ClubRecruitment::query()
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
                    ->where(function ($query) use ($childAges): void {
                        foreach ($childAges as $age) {
                            $query->orWhere('upto_age', '>=', $age);
                        }
                    })
                    ->orderBy('end_date')
                    ->limit(20)
                    ->get()

                    ->map(function (ClubRecruitment $recruitment) use ($children) {
                        $matchedChildren = $children
                            ->filter(function (array $child) use ($recruitment): bool {
                                return $child['age'] !== null && (int) $child['age'] <= (int) $recruitment->upto_age;
                            })
                            ->values();

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
                            ],
                            'experience' => $recruitment->experience,
                            'description' => $recruitment->description,
                            'upto_age' => $recruitment->upto_age,
                            'tryout_date' => $recruitment->end_date
                                ? ($recruitment->end_date instanceof CarbonInterface
                                    ? $recruitment->end_date->toDateString()
                                    : Carbon::parse($recruitment->end_date)->toDateString())
                                : null,
                            'matched_children' => $matchedChildren,
                        ];
                    })
                    ->values();
            }

            $upcomingPrograms = ProgramBooking::query()
                ->with([
                    'program:id,program_name,program_start,program_end,program_location,program_photo',
                    'athlete:id,name,last_name',
                    'bookingTime:id,time',
                ])
                ->where('parent_id', $user->id)
                ->where('payment_status', 'paid')
                ->where('status', '!=', 'cancelled')
                ->whereHas('program', function ($query) use ($today): void {
                    $query->whereDate('program_end', '>=', $today);
                })
                ->orderByDesc('id')
                ->get()
                ->sortBy(function (ProgramBooking $booking) {
                    return optional($booking->program?->program_start)->timestamp ?? PHP_INT_MAX;
                })
                ->values()
                ->map(function (ProgramBooking $booking) {
                    return [
                        'booking_id' => $booking->id,
                        'program' => [
                            'id' => $booking->program?->id,
                            'name' => $booking->program?->program_name,
                            'location' => $booking->program?->program_location,
                            'start_date' => optional($booking->program?->program_start)->toDateString(),
                            'end_date' => optional($booking->program?->program_end)->toDateString(),
                            'photo' => ! empty($booking->program?->program_photo) ? asset($booking->program->program_photo) : null,
                        ],
                        'child' => [
                            'id' => $booking->athlete?->id,
                            'name' => trim((string) ($booking->athlete?->name ?? '') . ' ' . (string) ($booking->athlete?->last_name ?? '')),
                        ],
                        'session_time' => $booking->bookingTime?->time,
                    ];
                })
                ->values();

            return $this->success([
                'summary' => [
                    'total_children' => $children->count(),
                    'total_upcoming_recruitments' => $upcomingRecruitments->count(),
                    'total_upcoming_programs' => $upcomingPrograms->count(),
                ],
                'recent_opportunities' => $upcomingRecruitments,
                'upcoming_program_reminders' => $upcomingPrograms,
            ], 'Parent dashboard data fetched successfully.', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }
}
