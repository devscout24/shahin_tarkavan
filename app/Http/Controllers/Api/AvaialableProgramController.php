<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\ErProgram;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AvaialableProgramController extends Controller
{
    public function listAvailablePrograms(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            $sportsName = trim((string) $request->query('sports_name', $request->input('sports_name', '')));

            if (! $user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Authentication required.',
                    'errors' => [],
                ], 401);
            }

            $profile = AthleteProfiles::query()
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhere('parent_id', $user->id);
                })
                ->latest('id')
                ->first();



            if (! $profile || ! $profile->dob) {
                return response()->json([
                    'status' => false,
                    'message' => 'Athlete profile not found or date of birth missing.',
                    'errors' => [],
                ], 404);
            }

            $age = Carbon::parse($profile->dob)->age;

            $resolveAgeGroup = function (?int $value): string {
                if ($value === null || $value > 18) {
                    return 'Senior Team';
                }

                if ($value <= 5) {
                    return 'U4/U5';
                }

                if ($value <= 7) {
                    return 'U6/U7';
                }

                return 'U' . $value;
            };

            $viewerAgeGroup = $resolveAgeGroup($age);

            $programQuery = ErProgram::query()
                ->with(['coach', 'times', 'goals'])
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->where('program_end', '>=', Carbon::now()->toDateString());

            if ($sportsName !== '') {
                $programQuery->where('sport', 'like', '%' . $sportsName . '%');
            }

            $programs = $programQuery
                ->get()
                ->filter(function (ErProgram $program) use ($viewerAgeGroup, $resolveAgeGroup) {
                    $programAgeGroup = $resolveAgeGroup($program->upto_age);

                    return $programAgeGroup === $viewerAgeGroup;
                })
                ->values()
                ->map(function (ErProgram $program) use ($resolveAgeGroup) {
                    $ageGroup = $resolveAgeGroup($program->upto_age);

                    return [
                        'id' => $program->id,
                        'program_name' => $program->program_name,
                        'sport' => $program->sport,
                        'program_price' => (float) $program->program_price,
                        'discount_price' => (float) $program->discount_price,
                        'upto_age' => $program->upto_age,
                        'age_group' => $ageGroup,
                        'program_location' => $program->program_location,
                        'program_start' => optional($program->program_start)?->toDateString(),
                        'program_end' => optional($program->program_end)?->toDateString(),
                        'program_photo' => $program->program_photo ? asset($program->program_photo) : null,
                        'coach_name' => trim(($program->coach?->name ?? '') . ' ' . ($program->coach?->last_name ?? '')),
                        'time' => $program->times->first()?->time,
                        'times' => $program->times->map(function ($time) {
                            return [
                                'id' => $time->id,
                                'time' => $time->time,
                            ];
                        })->values(),
                        'goals' => $program->goals->map(function ($goal) {
                            return [
                                'id' => $goal->id,
                                'goal' => $goal->goal,
                            ];
                        })->values(),
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Available programs fetched successfully',
                'errors' => [],
                'data' => [
                    'viewer' => [
                        'age' => $age,
                        'age_group' => $viewerAgeGroup,
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
}