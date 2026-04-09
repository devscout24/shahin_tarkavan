<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\CoachMedia;
use App\Models\CoachingTitle;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
            'sports' => 'required|string|max:255',
            'coaching_title' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user();
            $existingCoach = Coach::query()->where('user_id', $user?->id)->first();

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
                    'sports' => $request->sports,
                    'city' => $request->city ?? null,
                    'country' => $request->country ?? null,
                    'coach_profile_pic' => $coachProfilePicPath ?? null,
                    'current_role' => $request->current_role ?? null,
                    'years_of_experience' => $request->years_of_experience ?? null,
                    'highest_education' => $request->highest_education ?? null,
                    'coaching_education' => $request->coaching_education ?? null,
                    'coaching_philosophy' => $request->coaching_philosophy ?? null,
                    'player_centric_approach' => $request->boolean('player_centric_approach', false),
                    'data_driving_training' => $request->boolean('data_driving_training', false),
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
                ->where('user_id', $user->id)
                ->first();

            if (! $coach) {
                return $this->notFound([], 'Coach profile not found', 404);
            }

            $titles = $coach->coachingTitles->pluck('title')->toArray();
            // $avgRating = $coach->ratings()->avg('rating');
            $data = [
                'coach_id' => $coach->id,
                'visibility' => $coach->status === 'approve' ? 'public' : 'pending',
                'profile' => [
                    'name' => trim(($coach->name ?? '') . ' ' . ($coach->last_name ?? '')),
                    'sports' => $coach->sports,
                    'email' => $coach->email,
                    'nationality' => $coach->nationality,
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