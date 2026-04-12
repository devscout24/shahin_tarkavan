<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\ErProgram;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CoacherProgramController extends Controller
{
    use ApiResponse;

    private function mapProgramCard(ErProgram $program): array
    {
        $program->loadMissing(['coach', 'times', 'goals']);

        $coachName = trim(($program->coach?->name ?? '') . ' ' . ($program->coach?->last_name ?? ''));
        $firstTime = $program->times->first();

        return [
            'id' => $program->id,
            'program_name' => $program->program_name,
            'sport' => $program->sport,
            'program_price' => (float) $program->program_price,
            'discount_price' => (float) $program->discount_price,
            'upto_age' => $program->upto_age,
            'program_location' => $program->program_location,
            'program_start' => optional($program->program_start)?->toDateString(),
            'program_end' => optional($program->program_end)?->toDateString(),
            'program_photo' => $program->program_photo ? asset($program->program_photo) : null,
            'status' => (string) $program->status,
            'coach_name' => $coachName,
            'time' => $firstTime?->time,
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
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sport' => 'required|string|max:255',
            'program_name' => 'required|string|max:255',
            'program_price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'upto_age' => 'nullable|integer|min:0',
            'program_location' => 'nullable|string|max:255',
            'program_start' => 'nullable|date',
            'program_end' => 'nullable|date|after_or_equal:program_start',
            'about_program' => 'nullable|string',
            'program_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'program_time' => ['nullable', 'string'],
            'time' => ['nullable', 'string'],
            'time_label' => ['nullable', 'string'],
            'program_times' => 'nullable|array',
            'program_times.*' => 'nullable',
            'goals' => 'nullable|array',
            'goals.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user();

            if (! $user || $user->role !== 'coach') {
                DB::rollBack();
                return $this->forbidden([], 'Only coach accounts can manage programs.', 403);
            }

            $coach = Coach::query()->where('user_id', $user->id)->first();

            if (! $coach) {
                DB::rollBack();
                return $this->notFound([], 'Coach profile not found. Please create coach profile first.', 404);
            }

            $photoPath = null;
            if ($request->hasFile('program_photo')) {
                $file = $request->file('program_photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = 'uploads/er_programs/';
                $file->move(public_path($path), $filename);
                $photoPath = $path . $filename;
            }

            $program = ErProgram::query()->create([
                'coach_id' => $coach->id,
                'user_id' => $user->id,
                'sport' => $request->sport,
                'program_name' => $request->program_name,
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

            foreach ((array) $request->program_times as $time) {
                if (! empty($time)) {
                    $program->times()->create([
                        'time' => $time,
                    ]);
                }
            }

            foreach ((array) $request->goals as $goal) {
                if (! empty($goal)) {
                    $program->goals()->create([
                        'goal' => $goal,
                    ]);
                }
            }

            DB::commit();

            $program->load(['times', 'goals']);

            return $this->success($program, 'Program created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'sport' => 'required|string|max:255',
            'program_name' => 'required|string|max:255',
            'program_price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'upto_age' => 'nullable|integer|min:0',
            'program_location' => 'nullable|string|max:255',
            'program_start' => 'nullable|date',
            'program_end' => 'nullable|date|after_or_equal:program_start',
            'about_program' => 'nullable|string',
            'program_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'program_times' => 'nullable|array',
            'goals' => 'nullable|array',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user();
            if (! $user || $user->role !== 'coach') {
                DB::rollBack();
                return $this->forbidden([], 'Only coach accounts can manage programs.', 403);
            }

            $program = ErProgram::query()
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (! $program) {
                DB::rollBack();
                return $this->notFound([], 'Program not found.', 404);
            }

            if ($request->hasFile('program_photo')) {
                if ($program->program_photo && file_exists(public_path($program->program_photo))) {
                    unlink(public_path($program->program_photo));
                }

                $file = $request->file('program_photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = 'uploads/er_programs/';
                $file->move(public_path($path), $filename);
                $program->program_photo = $path . $filename;
            }

            $program->update([
                'sport' => $request->sport,
                'program_name' => $request->program_name,
                'program_price' => $request->program_price,
                'discount_price' => $request->discount_price ?? $program->discount_price,
                'upto_age' => $request->upto_age,
                'program_location' => $request->program_location,
                'program_start' => $request->program_start,
                'program_end' => $request->program_end,
                'about_program' => $request->about_program,
                'status' => $request->status ?? $program->status,
            ]);

            if ($request->has('program_times')) {
                $submittedTimeIds = [];

                foreach ((array) $request->program_times as $timeData) {
                    $timeId = null;
                    $timeValue = null;

                    if (is_array($timeData)) {
                        $timeId = $timeData['id'] ?? null;
                        $timeValue = $timeData['time'] ?? null;
                    } elseif (is_string($timeData)) {
                        $timeValue = $timeData;
                    }

                    if (! is_string($timeValue) || trim($timeValue) === '') {
                        continue;
                    }

                    $timeValue = trim($timeValue);

                    if (! empty($timeId)) {
                        $updated = $program->times()
                            ->where('id', $timeId)
                            ->update(['time' => $timeValue]);

                        if ($updated) {
                            $submittedTimeIds[] = (int) $timeId;
                            continue;
                        }
                    }

                    $newTime = $program->times()->create(['time' => $timeValue]);
                    $submittedTimeIds[] = $newTime->id;
                }

                if (! empty($submittedTimeIds)) {
                    $program->times()->whereNotIn('id', $submittedTimeIds)->delete();
                } else {
                    $program->times()->delete();
                }
            }

            if ($request->has('goals')) {
                $submittedGoalIds = [];

                foreach ((array) $request->goals as $goalData) {
                    $goalId = null;
                    $goalValue = null;

                    if (is_array($goalData)) {
                        $goalId = $goalData['id'] ?? null;
                        $goalValue = $goalData['goal'] ?? null;
                    } elseif (is_string($goalData)) {
                        $goalValue = $goalData;
                    }

                    if (! is_string($goalValue) || trim($goalValue) === '') {
                        continue;
                    }

                    $goalValue = trim($goalValue);

                    if (! empty($goalId)) {
                        $updated = $program->goals()
                            ->where('id', $goalId)
                            ->update(['goal' => $goalValue]);

                        if ($updated) {
                            $submittedGoalIds[] = (int) $goalId;
                            continue;
                        }
                    }

                    $newGoal = $program->goals()->create(['goal' => $goalValue]);
                    $submittedGoalIds[] = $newGoal->id;
                }

                if (! empty($submittedGoalIds)) {
                    $program->goals()->whereNotIn('id', $submittedGoalIds)->delete();
                } else {
                    $program->goals()->delete();
                }
            }

            DB::commit();

            $program->load(['times', 'goals']);

            return $this->success($program, 'Program updated successfully', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function list(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            if (!$user || $user->role !== 'coach') {
                return $this->forbidden([], 'Only coach accounts can view programs.', 403);
            }

            $coach = Coach::query()->where('user_id', $user->id)->first();
            if (!$coach) {
                return $this->notFound([], 'Coach profile not found.', 404);
            }

            $filter = strtolower(trim((string) $request->query('filter', 'all')));
            $search = trim((string) $request->query('search', ''));
            $perPage = (int) $request->query('per_page', 6);
            $perPage = $perPage > 0 ? min($perPage, 50) : 6;

            $programQuery = ErProgram::query()
                ->with(['coach', 'times', 'goals'])
                ->where('coach_id', $coach->id);

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

            // Latest upcoming program
            $latestUpcomingProgram = ErProgram::query()
                ->with(['coach', 'times', 'goals'])
                ->where('coach_id', $coach->id)
                ->whereDate('program_start', '>=', now()->toDateString())
                ->orderBy('program_start', 'asc')
                ->orderBy('created_at', 'desc')
                ->first();

            // Paginate programs
            $programs = $programQuery
                ->orderBy('created_at', 'desc')
                ->paginate($perPage)
                ->appends($request->query());

            // Pagination with URLs
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

            // Counts
            $counts = [
                'all' => ErProgram::query()->where('coach_id', $coach->id)->count(),
                'upcoming' => ErProgram::query()
                    ->where('coach_id', $coach->id)
                    ->whereDate('program_start', '>=', now()->toDateString())
                    ->count(),
                'active' => ErProgram::query()->where('coach_id', $coach->id)->where('status', 'active')->count(),
                'inactive' => ErProgram::query()->where('coach_id', $coach->id)->where('status', 'inactive')->count(),
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
            ], 'Programs fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function show($program_id)
    {
        try {
            $program = ErProgram::query()
                ->with([
                    'coach',
                    'times',
                    'goals',
                    'reviews.user:id,name,last_name,profile_image',
                ])
                ->find($program_id);

            if (! $program) {
                return $this->notFound([], 'Program not found.', 404);
            }

            $coachName = trim(($program->coach?->name ?? '') . ' ' . ($program->coach?->last_name ?? ''));

            $ratingsCount = $program->reviews->count();
            $averageRating = round((float) $program->reviews->avg('rating'), 2);

            $ratingBreakdown = [];
            foreach ([5, 4, 3, 2, 1] as $star) {
                $total = $program->reviews->where('rating', $star)->count();
                $ratingBreakdown[] = [
                    'star' => $star,
                    'total' => $total,
                    'percent' => $ratingsCount > 0 ? round(($total / $ratingsCount) * 100, 2) : 0,
                ];
            }

            $recentFeedback = $program->reviews
                ->sortByDesc('created_at')
                ->take(10)
                ->values()
                ->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'rating' => (int) $review->rating,
                        'review' => $review->review,
                        'created_at' => $review->created_at,
                        'reviewer' => [
                            'id' => $review->user?->id,
                            'name' => trim(($review->user?->name ?? '') . ' ' . ($review->user?->last_name ?? '')),
                            'profile_image' => $review->user?->profile_image ? asset($review->user->profile_image) : null,
                        ],
                    ];
                });

            return $this->success([
                'program' => [
                    'id' => $program->id,
                    'program_name' => $program->program_name,
                    'sport' => $program->sport,
                    'program_price' => (float) $program->program_price,
                    'discount_price' => (float) $program->discount_price,
                    'upto_age' => $program->upto_age,
                    'program_location' => $program->program_location,
                    'program_start' => optional($program->program_start)?->toDateString(),
                    'program_end' => optional($program->program_end)?->toDateString(),
                    'program_photo' => $program->program_photo ? asset($program->program_photo) : null,
                    'status' => $program->status,
                    'about_program' => $program->about_program,
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
                ],
                'coach' => [
                    'id' => $program->coach?->id,
                    'name' => $coachName,
                    'email' => $program->coach?->email,
                    'profile_image' => $program->coach?->coach_profile_pic ? asset($program->coach->coach_profile_pic) : null,
                    'bio' => $program->coach?->bio,
                    'title' => $program->coach?->coachingTitles->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'title' => $item->title,
                        ];
                    })->toArray(),
                ],

                'review_summary' => [
                    'average_rating' => $averageRating,
                    'total_reviews' => $ratingsCount,
                    'rating_breakdown' => $ratingBreakdown,
                ],
                'recent_feedback' => $recentFeedback,
            ], 'Program fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user();
            if (! $user || $user->role !== 'coach') {
                DB::rollBack();
                return $this->forbidden([], 'Only coach accounts can manage programs.', 403);
            }

            $program = ErProgram::query()
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (! $program) {
                DB::rollBack();
                return $this->notFound([], 'Program not found.', 404);
            }

            if ($program->program_photo && file_exists(public_path($program->program_photo))) {
                unlink(public_path($program->program_photo));
            }

            $program->delete();

            DB::commit();

            return $this->success([], 'Program deleted successfully', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errors([], $e->getMessage(), 500);
        }
    }


    public function submitReview(Request $request, $programId)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        try {
            $user = Auth::guard('api')->user();
            if (! $user) {
                return $this->forbidden([], 'Authentication required to submit a review.', 200);
            }

            if (! in_array((string) $user->role, ['parent', 'player'], true)) {
                return $this->forbidden([], 'Only parent or player can submit a review.', 403);
            }

            $program = ErProgram::query()
                ->with(['coach:id,allow_parent_player_reviews'])
                ->find($programId);

            if (! $program) {
                return $this->notFound([], 'Program not found.', 200);
            }

            if (! (bool) ($program->coach?->allow_parent_player_reviews ?? true)) {
                return $this->forbidden([], 'This coach does not allow parent/player reviews.', 403);
            }

            // Check if user has already submitted a review for this program
            $existingReview = $program->reviews()->where('user_id', $user->id)->first();
            if ($existingReview) {
                return $this->forbidden([], 'You have already submitted a review for this program.', 200);
            }

            // Create new review
            $program->reviews()->create([
                'user_id' => $user->id,
                'rating' => $request->rating,
                'review' => $request->review,
            ]);

            return $this->success([], 'Review submitted successfully', 200);
        } catch (\Exception $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }
}
