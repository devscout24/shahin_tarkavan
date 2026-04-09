<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\PlayerAchievement;
use App\Models\PlayerMediaLink;
use App\Models\PlayerMediaVideo;
use App\Models\PlayerPosition;
use App\Models\PlayerStrength;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Traits\ApiResponse;

class PlayerAuthController extends Controller
{
    use ApiResponse;
    public function PlayerRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'dob' => 'required',
            'nationality' => 'required',
            'sports_selection' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user();
            $child = new AthleteProfiles();
            $child->name = $request->name;
            $child->last_name = $request->last_name ?? null;
            $child->dob = $request->dob;
            $child->gender = $request->gender;
            $child->nationality = $request->nationality;
            $child->email = $request->email ?? null;
            $child->sports_selection = $request->sports_selection;
            $child->jersey_number = $request->jersey_number ?? null;
            $child->dominant_foot = $request->dominant_foot ?? null;
            $child->club_team = $request->club_team ?? null;
            $child->city= $request->city ?? null;
            $child->country= $request->country ?? null;
            // $child->parent_id = $user->id;
            $child->user_id = $user->id;

            $primaryPositionId = $this->resolvePositionId($request->primary_position);
            $secondaryPositionId = $this->resolvePositionId($request->secondary_position);

            if ($request->filled('primary_position') && $primaryPositionId === null) {
                DB::rollBack();
                return $this->validationError(['primary_position' => ['Invalid primary position.']], 422);
            }

            if ($request->filled('secondary_position') && $secondaryPositionId === null) {
                DB::rollBack();
                return $this->validationError(['secondary_position' => ['Invalid secondary position.']], 422);
            }

            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = 'uploads/athlete_profiles/';
                $filePath = $path . $filename;
                $file->move(public_path($path), $filename);
                $child->image = $filePath;
            }

            $child->primary_position = $primaryPositionId;
            $child->secondary_position = $secondaryPositionId;
            $child->athlete_biography = $request->athlete_biography ?? null;
            $child->privacy_settings = $request->privacy_settings ?? null;
            $child->total_played_games = $request->total_played_games ?? 0;
            $child->goals = $request->goals ?? 0;
            $child->assist = $request->assist ?? 0;
            $child->yellow_cards = $request->yellow_cards ?? 0;
            $child->red_cards = $request->red_cards ?? 0;
            $child->clean_sheets = $request->clean_sheets ?? 0;
            $child->total_saves = $request->total_saves ?? 0;
            $child->save();

            if ($request->has('strengths')) {
                $strengths = array_slice((array) $request->strengths, 0, 5);
                foreach ($strengths as $s) {
                    $strength = new PlayerStrength();
                    $strength->strength_type = $s['strength_type'] ?? null;
                    $strength->strength_name = $s['strength_name'] ?? null;
                    $strength->player_profile_id = $child->id;
                    $strength->save();
                }
            }

            if ($request->hasFile('reels')) {
                foreach ($request->file('reels') as $file) {

                    $mediaReel = new PlayerMediaVideo();
                    $mediaReel->player_profile_id = $child->id;
                    $mediaReel->status = 'reels';

                    // S3 upload
                    $path = Storage::disk('s3')->putFile("player/{$child->id}/reels", $file);
                    $url = Storage::disk('s3')->url($path);

                    $mediaReel->reels_video = $url;

                    $mediaReel->save();
                }
            }

            if ($request->has('link')) {
                foreach ((array) $request->link as $index => $link) {
                    $mediaLink = new PlayerMediaLink();
                    $mediaLink->player_profile_id = $child->id;
                    $mediaLink->status = $request->link_status[$index] ?? 'youtube';
                    $mediaLink->link = $link;
                    $mediaLink->save();
                }
            }

            if ($request->has('title')) {
                foreach ((array) $request->title as $index => $title) {
                    $achievement = new PlayerAchievement();
                    $achievement->title = $title;
                    $achievement->description = $request->description[$index] ?? null;
                    $achievement->date_earned = $request->date_earned[$index] ?? null;

                    if (isset($request->image[$index]) && $request->hasFile("image.$index")) {
                        $file = $request->file("image.$index");
                        $filename = time() . '_' . $file->getClientOriginalName();
                        $path = 'uploads/achievements/';
                        $filePath = $path . $filename;
                        $file->move(public_path($path), $filename);
                        $achievement->image = $filePath;
                    }

                    $achievement->player_id = $child->id;
                    $achievement->save();
                }
            }

            DB::commit();
            return $this->success($child, 'player added successfully',  200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errors($e->getMessage(), 500);
        }
    }




    public function updatePlayerProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required',
            'dob' => 'sometimes|required',
            'nationality' => 'sometimes|required',
            'sports_selection' => 'sometimes|required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user();

            $child = AthleteProfiles::query()
                ->where('user_id', $user->id)
                ->first();

            if (! $child) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Player not found',
                ], 404);
            }

            $child->name = $request->name ?? $child->name;
            $child->last_name = $request->last_name ?? $child->last_name;
            $child->dob = $request->dob ?? $child->dob;
            $child->gender = $request->gender ?? $child->gender;
            $child->nationality = $request->nationality ?? $child->nationality;
            $child->email = $request->email ?? $child->email;
            $child->sports_selection = $request->sports_selection ?? $child->sports_selection;
            $child->jersey_number = $request->jersey_number ?? $child->jersey_number;
            $child->dominant_foot = $request->dominant_foot ?? $child->dominant_foot;
            $child->club_team = $request->club_team ?? $child->club_team;
                $child->city= $request->city ?? $child->city;
                $child->country= $request->country ?? $child->country;
                // $child->parent_id = $user->id;
            if ($request->has('primary_position')) {
                $primaryPositionId = $this->resolvePositionId($request->primary_position);
                if ($request->filled('primary_position') && $primaryPositionId === null) {
                    DB::rollBack();
                    return $this->validationError(['primary_position' => ['Invalid primary position.']], 422);
                }
                $child->primary_position = $primaryPositionId;
            }

            if ($request->has('secondary_position')) {
                $secondaryPositionId = $this->resolvePositionId($request->secondary_position);
                if ($request->filled('secondary_position') && $secondaryPositionId === null) {
                    DB::rollBack();
                    return $this->validationError(['secondary_position' => ['Invalid secondary position.']], 422);
                }
                $child->secondary_position = $secondaryPositionId;
            }

            $child->athlete_biography = $request->athlete_biography ?? $child->athlete_biography;
            $child->privacy_settings = $request->privacy_settings ?? $child->privacy_settings;
            $child->total_played_games = $request->total_played_games ?? $child->total_played_games;
            $child->goals = $request->goals ?? $child->goals;
            $child->assist = $request->assist ?? $child->assist;
            $child->yellow_cards = $request->yellow_cards ?? $child->yellow_cards;
            $child->red_cards = $request->red_cards ?? $child->red_cards;
            $child->clean_sheets = $request->clean_sheets ?? $child->clean_sheets;
            $child->total_saves = $request->total_saves ?? $child->total_saves;

            if ($request->hasFile('profile_image')) {


                if ($child->image && file_exists(public_path($child->image))) {
                    unlink(public_path($child->image));
                }
                $file = $request->file('profile_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = 'uploads/athlete_profiles/';
                $filePath = $path . $filename;
                $file->move(public_path($path), $filename);
                $child->image = $filePath;
            }

            $child->save();

            if ($request->has('strengths')) {

                $strengths = array_slice((array) $request->strengths, 0, 5);


                $newNames = collect($strengths)->pluck('strength_name')->filter()->toArray();


                PlayerStrength::where('player_profile_id', $child->id)
                    ->whereNotIn('strength_name', $newNames)
                    ->delete();

                foreach ($strengths as $s) {
                    PlayerStrength::updateOrCreate(
                        [
                            'player_profile_id' => $child->id,
                            'strength_name' => $s['strength_name'] ?? null
                        ],
                        [
                            'strength_type' => $s['strength_type'] ?? null
                        ]
                    );
                }
            }

            if ($request->hasFile('reels')) {


                foreach ($request->file('reels') as $file) {
                    $mediaReel = new PlayerMediaVideo();
                    $mediaReel->player_profile_id = $child->id;
                    $mediaReel->status = 'reels';

                    $path = Storage::disk('s3')->putFile("player/{$child->id}/reels", $file);
                    $url = Storage::disk('s3')->url($path);

                    $mediaReel->reels_video = $url;
                    $mediaReel->save();
                }
            }

            if ($request->has('link')) {
                PlayerMediaLink::query()->where('player_profile_id', $child->id)->delete();

                foreach ((array) $request->link as $index => $link) {
                    $mediaLink = new PlayerMediaLink();
                    $mediaLink->player_profile_id = $child->id;
                    $mediaLink->status = $request->link_status[$index] ?? 'youtube';
                    $mediaLink->link = $link;
                    $mediaLink->save();
                }
            }

            if ($request->hasFile('profile_gallery')) {

                foreach ($request->file('profile_gallery') as $file) {
                    $mediaReel = new PlayerMediaVideo();
                    $mediaReel->player_profile_id = $child->id;
                    $mediaReel->status = 'image';
                    $gallery_image = $file->getClientOriginalName();
                    $path = "upload/profile/gallery";
                    $filePath = $path . $gallery_image;
                    $file->move(public_path($path), $gallery_image);
                    $mediaReel->image = $filePath;
                    $mediaReel->save();
                }
            }

            if ($request->has('title')) {


                foreach ((array) $request->title as $index => $title) {
                    $achievement = new PlayerAchievement();
                    $achievement->title = $title;
                    $achievement->description = $request->description[$index] ?? null;
                    $achievement->date_earned = $request->date_earned[$index] ?? null;

                    if (isset($request->image[$index]) && $request->hasFile("image.$index")) {
                        $file = $request->file("image.$index");
                        $filename = time() . '_' . $file->getClientOriginalName();
                        $path = 'uploads/achievements/';
                        $filePath = $path . $filename;
                        $file->move(public_path($path), $filename);
                        $achievement->image = $filePath;
                    }

                    $achievement->player_id = $child->id;
                    $achievement->save();
                }
            }

            DB::commit();
            return $this->success($child->fresh(), 'Athlete profile updated successfully',  200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errors($e->getMessage(), 500);
        }
    }

    private function resolvePositionId($position): ?int
    {
        if ($position === null || $position === '') {
            return null;
        }

        if (is_numeric($position)) {
            return PlayerPosition::query()->whereKey((int) $position)->exists()
                ? (int) $position
                : null;
        }

        return PlayerPosition::query()
            ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $position))])
            ->value('id');
    }
}