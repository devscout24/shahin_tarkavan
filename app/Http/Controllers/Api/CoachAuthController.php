<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Coach;
use App\Models\CoachMedia;
use App\Models\CoachingTitle;
use App\Models\Country;
use App\Models\SportOption;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CoachAuthController extends Controller
{
    use ApiResponse;

    public function AddUpdateCoachProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'dob' => 'required|date',
            'gender' => 'required|in:male,female',
            'nationality' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'country' => 'nullable|string',
            'city' => 'nullable|string',
            'city_id' => 'nullable|integer|exists:cities,id',
            'sport_option_id' => [
                'nullable',

            ],
            'sports' => 'nullable|string|max:255',
            'coaching_title' => 'required',
            'visible_reviews' => 'nullable|boolean',
            'allow_parent_player_reviews' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user();

            $existingCoach = Coach::query()->where('user_id', $user?->id)->first();
            $countryName = $request->filled('country') ? trim($request->input('country')) : ($request->country ?? null);
            $cityName = $request->filled('city') ? trim($request->input('city')) : ($request->city ?? null);

            $resolvedCityId = $cityName ? City::query()->whereRaw('LOWER(name) = ?', [strtolower(trim($cityName))])->value('id') : null;
            $resolvedCountryId = $countryName ? Country::query()->whereRaw('LOWER(name) = ?', [strtolower(trim($countryName))])->value('id') : null;

            $cityId = $resolvedCityId ? (int) $resolvedCityId : null;
            $countryId = $resolvedCountryId ? (int) $resolvedCountryId : null;

            if ($cityId && ! $countryId) {
                $countryId = City::query()->where('id', $cityId)->value('country_id');
            }

            if ($cityId && $countryId) {
                $cityBelongsToCountry = City::query()
                    ->where('id', $cityId)
                    ->where('country_id', $countryId)
                    ->exists();

                if (! $cityBelongsToCountry) {
                    DB::rollBack();

                    return $this->validationError(['city' => ['Selected city does not belong to selected country.']], 'Validation failed', 422);
                }
            }

            if (! $user || $user->role !== 'coach') {
                DB::rollBack();

                return $this->forbidden([], 'Only coach accounts can add a coach profile.', 403);
            }

            if ($request->hasFile('coach_profile_pic')) {

                if ($existingCoach && $existingCoach->coach_profile_pic && file_exists(public_path($existingCoach->coach_profile_pic))) {
                    unlink(public_path($existingCoach->coach_profile_pic));
                }

                $file = $request->file('coach_profile_pic');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = 'uploads/coach_profile_pics/';
                $file->move(public_path($path), $filename);
                $coachProfilePicPath = $path . $filename;
            }

            $sportOptionId = SportOption::resolveIdForAudience('coach', $request->input('sport_option_id', $request->input('sports')));
            $sportOptionName = $sportOptionId ? SportOption::query()->where('id', $sportOptionId)->value('name') : $request->input('sports');

            $coach = Coach::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $request->name,
                    'last_name' => $request->last_name ?? null,
                    'dob' => $request->dob,
                    'gender' => $request->gender,
                    'status' => $request->status ?? 'pending',
                    'nationality' => $request->nationality,
                    'email' => $request->email,
                    'sport_option_id' => $sportOptionId,
                    'sports' => $sportOptionName,
                    'city' => $cityName,
                    'city_id' => $cityId,
                    'country' => $countryName,
                    'country_id' => $countryId,
                    'coach_profile_pic' => $coachProfilePicPath ?? null,
                    'current_role' => $request->current_role ?? null,
                    'years_of_experience' => $request->years_of_experience ?? null,
                    'highest_education' => $request->highest_education ?? null,
                    'coaching_education' => $request->coaching_education ?? null,
                    'coaching_philosophy' => $request->coaching_philosophy ?? null,


                    'facebook_link' => $request->facebook_link,
                    'twitter_link' => $request->twitter_link,
                    'instagram_link' => $request->instagram_link,
                    'tiktok_link' => $request->tiktok_link,
                    'whatsapp_link' => $request->whatsapp_link,
                    'is_verified'=>true,


                    'player_centric_approach' => $request->boolean('player_centric_approach', false),
                    'data_driving_training' => $request->boolean('data_driving_training', false),
                    'visible_reviews' => $request->has('visible_reviews')
                        ? $request->boolean('visible_reviews')
                        : ($existingCoach?->visible_reviews ?? true),
                    'allow_parent_player_reviews' => $request->has('allow_parent_player_reviews')
                        ? $request->boolean('allow_parent_player_reviews')
                        : ($existingCoach?->allow_parent_player_reviews ?? true),
                ]
            );

            $coach->coachingTitles()->delete();

            foreach ((array) $request->coaching_title as $title) {
                CoachingTitle::create([
                    'coach_id' => $coach->id,
                    'user_id' => $user->id,
                    'title' => is_array($title) ? ($title['title'] ?? '') : $title,
                ]);
            }

            if ($request->hasFile('image')) {
                $existingMedia = $coach->media()->get();

                foreach ($existingMedia as $media) {
                    if ($media->image && file_exists(public_path($media->image))) {
                        unlink(public_path($media->image));
                    }
                }

                $coach->media()->delete();

                $images = $request->file('image');

                if (! is_array($images)) {
                    $images = [$images];
                }

                foreach ($images as $file) {
                    $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    $path = 'uploads/coach_media/';
                    $file->move(public_path($path), $filename);

                    CoachMedia::create([
                        'coach_id' => $coach->id,
                        'user_id' => $user->id,
                        'image' => $path . $filename,
                    ]);
                }
            }

            DB::commit();

            $coach->load(['user', 'coachingTitles', 'media', 'currentPosition:id,name']);

            return $this->success($coach, 'Coach profile created successfully', 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function getCoachProfile()
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user || $user->role !== 'coach') {
                return $this->forbidden([], 'Only coach accounts can view this profile.', 200);
            }

            $coach = Coach::query()
                ->with(['coachingTitles:id,coach_id,title', 'media:id,coach_id,image', 'currentPosition:id,name'])
                ->withAvg('programReviews', 'rating')
                ->withCount('programReviews')
                ->where('user_id', $user->id)
                ->first();

            if (! $coach) {
                return $this->notFound([], 'Coach profile not found', 404);
            }

            $titles = $coach->coachingTitles->pluck('title')->toArray();
            $overallAvgRating = $coach->visible_reviews
                ? round((float) ($coach->program_reviews_avg_rating ?? 0), 2)
                : null;

            $data = [
                'coach_id' => $coach->id,
                'user_id' => $coach->user_id,
                'user_status' => $coach->user?->status,
                'visibility' => $coach->status === 'approve' ? 'public' : 'pending',
                'profile' => [
                    'name' => trim(($coach->name ?? '') . ' ' . ($coach->last_name ?? '')),
                    'sport_option_id' => $coach->sport_option_id,
                    'sport_option' => $coach->sportOption
                        ? ['id' => (int) $coach->sportOption->id, 'name' => $coach->sportOption->name]
                        : null,
                    'sports' => $coach->sports,
                    'email' => $coach->email,
                    'nationality' => $coach->nationality,
                    'city_id' => $coach->city_id,
                    'country_id' => $coach->country_id,
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
                    'visible_reviews' => (bool) $coach->visible_reviews,
                    'is_verified'=>(bool) $coach->is_verified,
                    'allow_parent_player_reviews' => (bool) $coach->allow_parent_player_reviews,
                    'overall_avg_rating' => $overallAvgRating,
                    'total_reviews' => $coach->visible_reviews ? (int) $coach->program_reviews_count : 0,
                ],
                'coaching_titles' => $titles,
                'coach_media' => $coach->media
                    ->map(fn($item) => [
                        'id' => $item->id,
                        'image' => asset($item->image),
                    ])
                    ->values(),
                'experience_education' => [
                    [
                        'title' => $coach->current_role,
                        'duration' => $coach->years_of_experience,
                        'description' => $coach->coaching_education,
                    ],
                    [
                        'title' => $coach->highest_education,
                        'description' => $coach->coaching_education,
                    ],
                ],
                'badges' => array_values(array_filter([
                    $coach->player_centric_approach ? 'Player-Centric Approach' : null,
                    $coach->data_driving_training ? 'Data-Driven Progress Tracking' : null,
                ])),
            ];

            return $this->success($data, 'Coach profile fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function deleteCoachMedia($media_id)
    {
        $user = Auth::guard('api')->user();

        if (! $user || $user->role !== 'coach') {
            return $this->forbidden([], 'Only coach accounts can delete media.', 403);
        }

        $media = CoachMedia::query()->where('id', $media_id)->where('user_id', $user->id)->first();

        if (! $media) {
            return $this->notFound([], 'Media not found', 404);
        }

        if ($media->image && file_exists(public_path($media->image))) {
            unlink(public_path($media->image));
        }

        $media->delete();

        return $this->success([], 'Media deleted successfully', 200);
    }

    public function coachList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:100',
            'sport' => 'nullable|string|max:100',
            'position_id' => 'nullable|integer|exists:coach_positions,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        try {
            $search = trim((string) $request->input('search', ''));
            $sport = trim((string) $request->input('sport', ''));
            $positionId = $request->input('position_id');

            $query = Coach::query()
                ->with([
                    'coachingTitles:id,coach_id,title',
                    'media:id,coach_id,image',
                    'currentPosition:id,name',
                ])
                ->withAvg('programReviews', 'rating')
                ->withCount('programReviews')
                ->where('status', 'approve')
                ->latest('id');

            if ($search !== '') {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhere('sports', 'like', '%' . $search . '%')
                        ->orWhere('city', 'like', '%' . $search . '%')
                        ->orWhere('country', 'like', '%' . $search . '%')
                        ->orWhereHas('currentPosition', function ($positionQuery) use ($search): void {
                            $positionQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            }

            if ($sport !== '') {
                $query->where('sports', 'like', '%' . $sport . '%');
            }

            if (! empty($positionId)) {
                $query->where('current_role', (int) $positionId);
            }

            $items = $query->get()->map(function (Coach $coach) {
                return [
                    'id' => $coach->id,
                    'name' => trim((string) $coach->name . ' ' . (string) $coach->last_name),
                    'sport_option_id' => $coach->sport_option_id,
                    'sport_option' => $coach->sportOption
                        ? ['id' => (int) $coach->sportOption->id, 'name' => $coach->sportOption->name]
                        : null,
                    'sports' => $coach->sports,
                    'email' => $coach->email,
                    'nationality' => $coach->nationality,
                    'city' => $coach->city,
                    'city_id' => $coach->city_id,
                    'country' => $coach->country,
                    'country_id' => $coach->country_id,
                    'profile_image' => ! empty($coach->coach_profile_pic) ? asset($coach->coach_profile_pic) : null,
                    'current_role' => $coach->currentPosition
                        ? ['id' => (int) $coach->currentPosition->id, 'name' => $coach->currentPosition->name]
                        : null,
                    'years_of_experience' => $coach->years_of_experience,
                    'visible_reviews' => (bool) $coach->visible_reviews,
                    'allow_parent_player_reviews' => (bool) $coach->allow_parent_player_reviews,
                    'overall_avg_rating' => (bool) $coach->visible_reviews
                        ? round((float) ($coach->program_reviews_avg_rating ?? 0), 2)
                        : null,
                    'total_reviews' => (bool) $coach->visible_reviews
                        ? (int) $coach->program_reviews_count
                        : 0,
                    'coaching_titles' => $coach->coachingTitles
                        ->pluck('title')
                        ->filter()
                        ->values(),
                    'media' => $coach->media
                        ->map(fn($item) => [
                            'id' => $item->id,
                            'image' => ! empty($item->image) ? asset($item->image) : null,
                        ])
                        ->values(),
                ];
            })->values();

            return $this->success([
                'items' => $items,
            ], 'Coach list fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function editdata()
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user || $user->role !== 'coach') {
                return $this->forbidden([], 'Only coach accounts can view this profile.', 200);
            }

            $coach = Coach::query()
                ->with(['coachingTitles:id,coach_id,title', 'media:id,coach_id,image'])
                ->where('user_id', $user->id)
                ->first();

            if (! $coach) {
                return $this->notFound([], 'Coach profile not found', 404);
            }

            return $this->success($coach, 'Coach profile fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }
}