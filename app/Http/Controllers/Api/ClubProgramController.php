<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubSubscription;
use App\Models\ErProgram;
use App\Models\SportOption;
use App\Traits\ApiResponse;
use App\Traits\ProgramProviderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClubProgramController extends Controller
{
    use ApiResponse, ProgramProviderTrait;

    private function normalizeNestedValues(Request $request, string $key): array
    {
        $values = $request->input($key);

        if (is_array($values)) {
            return collect($values)
                ->map(function ($value): string {
                    if (is_array($value)) {
                        return trim((string) ($value['value'] ?? $value['name'] ?? $value['goal'] ?? $value['time'] ?? ''));
                    }

                    return trim((string) $value);
                })
                ->filter()
                ->values()
                ->all();
        }

        return collect($request->all())
            ->filter(function ($value, $inputKey) use ($key): bool {
                return is_string($inputKey) && str_starts_with($inputKey, $key . '[');
            })
            ->map(function ($value): string {
                if (is_array($value)) {
                    return trim((string) ($value['value'] ?? $value['name'] ?? $value['goal'] ?? $value['time'] ?? ''));
                }

                return trim((string) $value);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeProgramTimes(Request $request): array
    {
        $times = $request->input('program_times');

        if (! is_array($times)) {
            return collect($this->normalizeNestedValues($request, 'program_times'))
                ->map(function (string $time): array {
                    return [
                        'time' => $time,
                        'slot_date' => null,
                        'start_time' => null,
                        'end_time' => null,
                    ];
                })
                ->values()
                ->all();
        }

        return collect($times)
            ->map(function ($value): array {
                if (is_array($value)) {
                    $time = trim((string) ($value['time'] ?? $value['label'] ?? ''));
                    $slotDate = trim((string) ($value['slot_date'] ?? $value['date'] ?? ''));
                    $startTime = trim((string) ($value['start_time'] ?? ''));
                    $endTime = trim((string) ($value['end_time'] ?? ''));

                    return [
                        'time' => $time !== '' ? $time : null,
                        'slot_date' => $slotDate !== '' ? $slotDate : null,
                        'start_time' => $startTime !== '' ? $startTime : null,
                        'end_time' => $endTime !== '' ? $endTime : null,
                    ];
                }

                $time = trim((string) $value);

                return [
                    'time' => $time !== '' ? $time : null,
                    'slot_date' => null,
                    'start_time' => null,
                    'end_time' => null,
                ];
            })
            ->filter(function (array $slot): bool {
                return (string) ($slot['time'] ?? '') !== ''
                    || ! empty($slot['slot_date'])
                    || ! empty($slot['start_time'])
                    || ! empty($slot['end_time']);
            })
            ->values()
            ->all();
    }

    private function mapProgramTimeSlot($time): array
    {
        return [
            'id' => $time->id,
            'time' => $time->time,
            'slot_date' => optional($time->slot_date)?->toDateString(),
            'start_time' => $time->start_time,
            'end_time' => $time->end_time,
            'is_available' => (bool) ($time->is_available ?? true),
        ];
    }

    private function resolveClubName(ErProgram $program): string
    {
        return trim((string) ($program->user?->club?->club_name ?? $program->user?->name ?? ''));
    }

    private function mapProgramCard(ErProgram $program): array
    {
        return $this->formatProgramData($program);
    }

    public function store(Request $request)
    {


        //   dd($request->all());

        $validator = Validator::make($request->all(), [
            'sport_option_id' => [
                'nullable',
            ],
            'sport' => 'nullable|string|max:255',
            'program_name' => 'required|string|max:255',
            'program_type' => 'nullable|string|in:one_one,group',
            'program_price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'upto_age' => 'nullable|integer|min:0',
            'program_location' => 'nullable|string|max:255',
            'program_start' => 'nullable|date',
            'program_end' => 'nullable|date|after_or_equal:program_start',
            'about_program' => 'nullable|string',
            'program_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'program_times' => 'nullable|array',
            'program_times.*' => 'nullable',
            'program_times.*.time' => 'nullable|string|max:255',
            'program_times.*.slot_date' => 'nullable|date',
            'program_times.*.start_time' => 'nullable|date_format:H:i',
            'program_times.*.end_time' => 'nullable|date_format:H:i',
            'goals' => 'nullable|array',
            'goals.*' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,active,inactive,upcoming',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user();

            if (! $user || $user->role !== 'club') {
                DB::rollBack();
                return $this->forbidden([], 'Only club accounts can manage club programs.', 403);
            }

            $activeSubscription = ClubSubscription::query()
                ->where('club_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (! $activeSubscription) {
                DB::rollBack();
                return $this->notFound([], 'Active club subscription not found.', 404);
            }

            $photoPath = null;
            if ($request->hasFile('program_photo')) {
                $file = $request->file('program_photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = 'uploads/club_programs/';
                $file->move(public_path($path), $filename);
                $photoPath = $path . $filename;
            }



            $program = ErProgram::query()->create([
                'coach_id' => null,
                'user_id' => $user->id,
                'sport_option_id' => $request->sport_option_id,
                'sport' => $request->sport,
                'program_name' => $request->program_name,
                'program_type' => $request->input('program_type', 'one_one'),
                'program_price' => $request->program_price,
                'discount_price' => $request->discount_price ?? 0,
                'upto_age' => $request->upto_age,
                'program_location' => $request->program_location,
                'program_start' => $request->program_start,
                'program_end' => $request->program_end,
                'about_program' => $request->about_program,
                'program_photo' => $photoPath,
                'status' => $request->status ?? 'active',
            ]);

            $programTimes = $this->normalizeProgramTimes($request);
            foreach ($programTimes as $slot) {
                $program->times()->create([
                    'time' => $slot['time'],
                    'slot_date' => $slot['slot_date'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'is_available' => true,
                ]);
            }

            $goals = $this->normalizeNestedValues($request, 'goals');
            foreach ($goals as $goal) {
                $program->goals()->create([
                    'goal' => $goal,
                ]);
            }

            DB::commit();

            return $this->success($program->load(['sportOption:id,name', 'times', 'goals', 'user:id,name,last_name,profile_image,role', 'user.club:id,user_id,club_name,club_logo,city,country']), 'Club program created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'sport_option_id' => [
                'nullable',

            ],
            'sport' => 'nullable|string|max:255',
            'program_name' => 'nullable|string|max:255',
            'program_type' => 'nullable|string|in:one_one,group',
            'program_price' => 'nullable|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'upto_age' => 'nullable|integer|min:0',
            'program_location' => 'nullable|string|max:255',
            'program_start' => 'nullable|date',
            'program_end' => 'nullable|date|after_or_equal:program_start',
            'about_program' => 'nullable|string',
            'program_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'program_times' => 'nullable|array',
            'program_times.*' => 'nullable',
            'goals' => 'nullable|array',
            'goals.*' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,active,inactive,upcoming',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user();
            if (! $user || $user->role !== 'club') {
                DB::rollBack();
                return $this->forbidden([], 'Only club accounts can manage club programs.', 403);
            }

            $program = ErProgram::query()
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->whereNull('coach_id')
                ->first();

            if (! $program) {
                DB::rollBack();
                return $this->notFound([], 'Club program not found.', 404);
            }

            if ($request->hasFile('program_photo')) {
                if ($program->program_photo && file_exists(public_path($program->program_photo))) {
                    unlink(public_path($program->program_photo));
                }

                $file = $request->file('program_photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = 'uploads/club_programs/';
                $file->move(public_path($path), $filename);
                $program->program_photo = $path . $filename;
            }

            // $sportOptionId = $request->filled('sport_option_id')
            //     ? SportOption::resolveIdForAudience('coach', $request->input('sport_option_id'))
            //     : SportOption::resolveIdForAudience('coach', $request->input('sport'));

            // $sportName = $sportOptionId
            //     ? SportOption::resolveNameForAudience('coach', $sportOptionId)
            //     : null;

            // if ($sportName === null) {
            //     $rawSport = $request->input('sport');
            //     if (is_numeric($rawSport)) {
            //         DB::rollBack();
            //         return $this->validationError(['sport' => ['Invalid sport option selected.']], 'Validation failed', 422);
            //     }

            //     $sportName = is_string($rawSport) && trim($rawSport) !== '' ? trim($rawSport) : $program->sport;
            // }

            $program->update([
                'sport_option_id' => $request->sport_option_id ?? $program->sport_option_id,
                'sport' => $request->sport?? $program->sport,
                'program_name' => $request->program_name?? $program->program_name,
                'program_type' => $request->input('program_type', $program->program_type)?? $program->program_type,
                'program_price' => $request->program_price?? $program->program_price,
                'discount_price' => $request->discount_price ?? $program->discount_price,
                'upto_age' => $request->upto_age?? $program->upto_age,
                'program_location' => $request->program_location?? $program->program_location,
                'program_start' => $request->program_start?? $program->program_start,
                'program_end' => $request->program_end?? $program->program_end,
                'about_program' => $request->about_program?? $program->about_program,
                'status' => $request->status ?? $program->status,
            ]);

            if ($request->has('program_times')) {
                $submittedTimeIds = [];
                $programTimes = $this->normalizeProgramTimes($request);

                $program->times()->delete();

                foreach ($programTimes as $slot) {
                    $newTime = $program->times()->create([
                        'time' => $slot['time'],
                        'slot_date' => $slot['slot_date'],
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                        'is_available' => true,
                    ]);
                    $submittedTimeIds[] = $newTime->id;
                }
            }

            if ($request->has('goals')) {
                $submittedGoals = [];
                $goals = $this->normalizeNestedValues($request, 'goals');

                $program->goals()->delete();

                foreach ($goals as $goal) {
                    $newGoal = $program->goals()->create([
                        'goal' => $goal,
                    ]);
                    $submittedGoals[] = $newGoal->id;
                }
            }

            DB::commit();

            return $this->success($program->fresh()->load(['sportOption:id,name', 'times', 'goals', 'user:id,name,last_name,profile_image,role', 'user.club:id,user_id,club_name,club_logo,city,country']), 'Club program updated successfully', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function list(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            if (! $user || $user->role !== 'club') {
                return $this->forbidden([], 'Only club accounts can view club programs.', 403);
            }

            $club = $user->club;
            if (! $club) {
                return $this->notFound([], 'Club profile not found.', 404);
            }

            $filter = strtolower(trim((string) $request->query('filter', 'all')));
            $search = trim((string) $request->query('search', ''));
            $perPage = (int) $request->query('per_page', 6);
            $perPage = $perPage > 0 ? min($perPage, 50) : 6;

            $programQuery = ErProgram::query()
                ->with(['sportOption:id,name', 'times', 'goals', 'user:id,name,last_name,profile_image,role', 'user.club:id,user_id,club_name,club_logo,city,country'])
                ->where('user_id', $user->id)
                ->whereNull('coach_id');

            if ($search !== '') {
                $programQuery->where(function ($query) use ($search) {
                    $query->where('program_name', 'like', '%' . $search . '%')
                        ->orWhere('sport', 'like', '%' . $search . '%')
                        ->orWhere('program_location', 'like', '%' . $search . '%');
                });
            }

            if ($filter === 'upcoming') {
                $programQuery->whereDate('program_start', '>=', now()->toDateString());
            } elseif ($filter === 'active') {
                $programQuery->where('status', 'active');
            } elseif (in_array($filter, ['inactive', 'deactive', 'deactivated'], true)) {
                $programQuery->where('status', 'inactive');
            }

            $latestUpcomingProgram = ErProgram::query()
                ->with(['sportOption:id,name', 'times', 'goals', 'user:id,name,last_name,profile_image,role', 'user.club:id,user_id,club_name,club_logo,city,country'])
                ->where('user_id', $user->id)
                ->whereNull('coach_id')
                ->whereDate('program_start', '>=', now()->toDateString())
                ->orderBy('program_start', 'asc')
                ->orderBy('created_at', 'desc')
                ->first();

            $programs = $programQuery
                ->orderBy('created_at', 'desc')
                ->paginate($perPage)
                ->appends($request->query());

            $pagination = [
                'current_page' => $programs->currentPage(),
                'last_page' => $programs->lastPage(),
                'per_page' => $programs->perPage(),
                'total' => $programs->total(),
                'first_page_url' => $programs->url(1),
                'last_page_url' => $programs->url($programs->lastPage()),
                'next_page_url' => $programs->nextPageUrl(),
                'prev_page_url' => $programs->previousPageUrl(),
            ];

            $counts = [
                'all' => ErProgram::query()->where('user_id', $user->id)->whereNull('coach_id')->count(),
                'upcoming' => ErProgram::query()
                    ->where('user_id', $user->id)
                    ->whereNull('coach_id')
                    ->whereDate('program_start', '>=', now()->toDateString())
                    ->count(),
                'active' => ErProgram::query()->where('user_id', $user->id)->whereNull('coach_id')->where('status', 'active')->count(),
                'inactive' => ErProgram::query()->where('user_id', $user->id)->whereNull('coach_id')->where('status', 'inactive')->count(),
            ];

            return $this->success([
                'latest_upcoming_program' => $latestUpcomingProgram ? $this->mapProgramCard($latestUpcomingProgram) : null,
                'programs' => collect($programs->items())->map(fn($program) => $this->mapProgramCard($program))->values(),
                'pagination' => $pagination,
                'filters' => [
                    'filter' => $filter,
                    'search' => $search,
                ],
                'counts' => $counts,
            ], 'Club programs fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

   public function show($program_id)
{
    try {
        $user = Auth::guard('api')->user();

        if (! $user || $user->role !== 'club') {
            return $this->forbidden([], 'Only club accounts can view club programs.', 403);
        }

        $program = ErProgram::query()
            ->with([
                'sportOption:id,name',
                'times',
                'goals',
                'user:id,name,last_name,profile_image,role',
                'user.club:id,user_id,club_name,club_logo,city,country',
                'reviews.user:id,name,last_name,profile_image'
            ])
            ->where('id', $program_id)
            ->where('user_id', $user->id)
            ->whereNull('coach_id')
            ->first();

        if (! $program) {
            return $this->notFound([], 'Club program not found.', 404);
        }

        $data = $this->formatProgramData($program);

        // ⭐ Review Summary Add
        $data['reviews_summary'] = [
            'average_rating' => (float) ($program->reviews()->avg('rating') ?? 0),
            'total_reviews' => $program->reviews()->count(),
            'rating_distribution' => [
                '5' => $program->reviews()->where('rating', 5)->count(),
                '4' => $program->reviews()->where('rating', 4)->count(),
                '3' => $program->reviews()->where('rating', 3)->count(),
                '2' => $program->reviews()->where('rating', 2)->count(),
                '1' => $program->reviews()->where('rating', 1)->count(),
            ],
        ];

        // ⭐ Recent reviews
        $data['recent_feedback'] = $program->reviews()
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($review) {
                return [
                    'user_name' => $review->user->name ?? 'Anonymous',
                    'user_image' => $review->user && $review->user->profile_image
                        ? asset($review->user->profile_image)
                        : null,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'date' => $review->created_at->format('M d, Y'),
                ];
            });

        return $this->success([
            'program' => $data,
        ], 'Club program fetched successfully', 200);

    } catch (\Exception $e) {
        return $this->errors([], $e->getMessage(), 500);
    }
}

    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user();
            if (! $user || $user->role !== 'club') {
                DB::rollBack();
                return $this->forbidden([], 'Only club accounts can manage club programs.', 403);
            }

            $program = ErProgram::query()
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->whereNull('coach_id')
                ->first();

            if (! $program) {
                DB::rollBack();
                return $this->notFound([], 'Club program not found.', 404);
            }

            if ($program->program_photo && file_exists(public_path($program->program_photo))) {
                unlink(public_path($program->program_photo));
            }

            $program->delete();

            DB::commit();

            return $this->success([], 'Club program deleted successfully', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errors([], $e->getMessage(), 500);
        }
    }
}
