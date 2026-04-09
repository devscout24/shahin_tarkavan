<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\Endorse;
use App\Models\PlayerAchievement;
use App\Models\PlayerMediaLink;
use App\Models\PlayerMediaVideo;
use App\Models\PlayerPosition;
use App\Models\PlayerStrength;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ParentChildController extends Controller
{
    use ApiResponse;

    public function addChild(Request $request)
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
            $child->parent_id = $user->id;
            $child->city= $request->city ?? null;
            $child->country= $request->country ?? null;
            // $child->user_id = $user->id;

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
            return $this->success($child, 'Child added successfully',  200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function listChildren()
    {
        $user = Auth::guard('api')->user();

        $children = AthleteProfiles::query()
            ->with(['primaryPosition:id,name', 'secondaryPosition:id,name'])
            ->where('parent_id', $user->id)
            ->latest('id')
            ->get();

        $data = $children->map(function ($child) {
            return [
                'id' => $child->id,
                'name' => $child->name,
                'last_name' => $child->last_name,
                'dob' => $child->dob,
                'image' => asset($child->image) ?? null,
                'primary_position' => $child->primaryPosition
                    ? ['id' => (int) $child->primaryPosition->id, 'name' => $child->primaryPosition->name]
                    : null,
                'secondary_position' => $child->secondaryPosition
                    ? ['id' => (int) $child->secondaryPosition->id, 'name' => $child->secondaryPosition->name]
                    : null,
                'jersey_number' => $child->jersey_number,
                'tolal_played_games' => $child->total_played_games,
                'goals' => $child->goals,
                'assist' => $child->assist,


                'age' => Carbon::parse($child->dob)->age,
                'parent_control' => "Parent control active",
            ];
        });



        return $this->success($data, 'Children fetched successfully',  200);
    }

    public function updateAthleteProfile(Request $request, int $child_id)
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
                ->where('id', $child_id)
                ->where('parent_id', $user->id)
                ->first();

            if (! $child) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Child not found',
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
            return $this->success('Athlete profile updated successfully', $child->fresh(), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function removeChild(int $child_id)
    {
        $user = Auth::guard('api')->user();

        $child = AthleteProfiles::query()
            ->where('id', $child_id)
            ->where('parent_id', $user->id)
            ->first();

        if ($child->user_id) {
            $childUser = User::find($child->user_id);
            if ($childUser) {
                $childUser->delete();
            }
        }


        if (! $child) {
            return response()->json([
                'status' => false,
                'message' => 'Child not found',
            ], 404);
        }

        $child->delete();

        return $this->success([], 'Child removed successfully', 200);
    }


    public function deleteGalleryMedia($media_id)
    {


        try {
            $media = PlayerMediaVideo::find($media_id);
            if (!$media) {
                return response()->json([

                    'status' => false,
                    'message' => 'Media not found',
                ], 200);
            }

            if ($media->image && file_exists(public_path($media->image))) {
                unlink(public_path($media->image));
            }
            $media->delete();
            return $this->success('Media deleted successfully', (object) [], 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function deleteReelMedia($media_id)
    {
        try {
            $media = PlayerMediaVideo::find($media_id);

            if (!$media) {
                return response()->json([
                    'status' => false,
                    'message' => 'Media not found',
                ], 200);
            }

            // 👉 S3 file delete
            if ($media->reels_video) {

                // URL থেকে path বের করতে হবে
                $path = parse_url($media->reels_video, PHP_URL_PATH);

                // শুরুতে "/" থাকে → remove করতে হবে
                $path = ltrim($path, '/');

                Storage::disk('s3')->delete($path);
            }

            // 👉 DB delete
            $media->delete();

            return $this->success('Media deleted successfully', (object) [], 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function deleteAchievement($achievement_id)
    {
        try {
            $achievement = PlayerAchievement::find($achievement_id);
            if (!$achievement) {
                return response()->json([
                    'status' => false,
                    'message' => 'Achievement not found',
                ], 200);
            }

            if ($achievement->image && file_exists(public_path($achievement->image))) {
                unlink(public_path($achievement->image));
            }
            $achievement->delete();
            return $this->success('Achievement deleted successfully', (object) [], 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }


    public function endorseStrength(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'player_strength_id' => 'required',
            'athlete_profile_id' => 'required',
            'strength_count' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {

            $userId = Auth::id();


            $alreadyEndorsed = Endorse::where('player_strength_id', $request->player_strength_id)
                ->where('athlete_profile_id', $request->athlete_profile_id)
                ->where('endorced_by', $userId)
                ->first();

            if ($alreadyEndorsed) {
                return response()->json([
                    'status' => false,
                    'message' => 'You already endorsed this strength',
                ], 400);
            }

            // 👉 create new endorse
            $endorse = new Endorse();
            $endorse->player_strength_id = $request->player_strength_id;
            $endorse->athlete_profile_id = $request->athlete_profile_id;
            $endorse->strength_count = (int) $request->strength_count;
            $endorse->endorced_by = $userId;
            $endorse->save();

            return $this->success('Strength endorsed successfully', $endorse, 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function getProfileData(int $child_id)
    {
        try {
            $viewer = Auth::guard('api')->user();

            $profile = AthleteProfiles::query()
                ->with(['strengths', 'mediaReels', 'mediaLinks', 'achievements', 'primaryPosition:id,name', 'secondaryPosition:id,name'])
                ->where('id', $child_id)
                ->first();

            if (!$profile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found',
                ], 404);
            }

            if (! $this->canViewAthleteProfile($viewer, $profile)) {
                return $this->forbidden([], 'This player profile is private.', 403);
            }

            // 1. BASIC PROFILE INFO
            $basicInfo = [
                'id' => $profile->id,
                'name' => $profile->name,
                'last_name' => $profile->last_name,
                'full_name' => $profile->name . ' ' . $profile->last_name,
                'dob' => $profile->dob,
                'age' => Carbon::parse($profile->dob)->age,
                'gender' => $profile->gender,
                'nationality' => $profile->nationality,
                'email' => $profile->email,
                'image' => $profile->image ? asset($profile->image) : null,
                'biography' => $profile->athlete_biography,
                'privacy_settings' => $profile->privacy_settings,
            ];

            // 2. PLAYER STATS
            $playerStats = [
                'total_matches' => $profile->total_played_games ?? 0,
                'goals' => $profile->goals ?? 0,
                'assists' => $profile->assist ?? 0,
                'yellow_cards' => $profile->yellow_cards ?? 0,
                'red_cards' => $profile->red_cards ?? 0,
                'clean_sheets' => $profile->clean_sheets ?? 0,
                'total_saves' => $profile->total_saves ?? 0,
            ];

            // 3. STRENGTHS WITH ENDORSEMENT COUNT
            $strengthsWithEndorse = $profile->strengths->map(function ($strength) {
                $endorseCount = Endorse::where('player_strength_id', $strength->id)
                    ->count();

                return [
                    'id' => $strength->id,
                    'strength_type' => $strength->strength_type,
                    'strength_name' => $strength->strength_name,
                    'endorse_count' => $endorseCount,
                    'endorsed' => $endorseCount > 0 ? true : false,
                ];
            });

            // 4. ACHIEVEMENTS
            $achievements = $profile->achievements->map(function ($achievement) {
                return [
                    'id' => $achievement->id,
                    'title' => $achievement->title,
                    'description' => $achievement->description,
                    'date_earned' => $achievement->date_earned,
                    'image' => $achievement->image ? asset($achievement->image) : null,
                ];
            });

            // 5. IMAGES/GALLERY (PlayerMediaVideo with status='image')
            $gallery = PlayerMediaVideo::where('player_profile_id', $profile->id)
                ->where('status', 'image')
                ->get()
                ->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'image' => $media->image ? asset($media->image) : null,
                        'uploaded_at' => $media->created_at,
                    ];
                });

            // 6. VIDEOS/REELS (PlayerMediaVideo with status='reels')
            $reels = $profile->mediaReels
                ->where('status', 'reels')
                ->values()
                ->map(function ($reel) {
                    return [
                        'id' => $reel->id,
                        'video_url' => $reel->reels_video,
                        'status' => $reel->status,
                        'uploaded_at' => $reel->created_at,
                    ];
                });

            // 7. MEDIA LINKS (YouTube, Hudi Profile, etc.)
            $mediaLinks = $profile->mediaLinks->map(function ($link) {
                return [
                    'id' => $link->id,
                    'link' => $link->link,
                    'status' => $link->status,
                    'image' => $link->image ? asset($link->image) : null,
                ];
            });

            // 8. POSITION & CLUB INFO
            $positionInfo = [
                'primary_position' => $profile->primaryPosition
                    ? ['id' => (int) $profile->primaryPosition->id, 'name' => $profile->primaryPosition->name]
                    : null,
                'secondary_position' => $profile->secondaryPosition
                    ? ['id' => (int) $profile->secondaryPosition->id, 'name' => $profile->secondaryPosition->name]
                    : null,
                'jersey_number' => $profile->jersey_number,
                'club_team' => $profile->club_team,
                'dominant_foot' => $profile->dominant_foot,
                'sports_selection' => $profile->sports_selection,
            ];

            // COMBINED RESPONSE
            $completeProfile = [
                'basic_info' => $basicInfo,
                'position_info' => $positionInfo,
                'player_stats' => $playerStats,
                'strengths' => $strengthsWithEndorse,
                'achievements' => $achievements,
                'gallery' => $gallery,
                'videos' => $reels,
                'media_links' => $mediaLinks,
            ];

            return $this->success($completeProfile, 'Profile data fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }


    public function getPlayerData(int $child_id)
    {
        try {
            $viewer = Auth::guard('api')->user();

            $profile = AthleteProfiles::query()
                ->with(['strengths', 'mediaReels', 'mediaLinks', 'achievements', 'primaryPosition:id,name', 'secondaryPosition:id,name'])
                ->where('id', $child_id)
                ->first();

            if (!$profile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found',
                ], 200);
            }

            if (! $this->canViewAthleteProfile($viewer, $profile)) {
                return $this->forbidden([], 'This player profile is private.', 403);
            }

            return $this->success($profile, 'Player data fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function sendInvitation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_id' => 'required|exists:athlete_profiles,id',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $child = AthleteProfiles::find($request->child_id);
            if (!$child->user_id) {
                $user = new User();
                $user->name = $child->name . ' ' . $child->last_name;
                $user->email = $request->email;
                $user->password = bcrypt('defaultpassword'); // You should generate a random password and send it in the email
                $user->save();

                $child->user_id = $user->id;
                $child->save();
            }

            Mail::raw("You have been invited to join Tarkaven as a player. Please use the following email to login: {$request->email} and password: {$request->password}", function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('Tarkaven Player Invitation');
            });

            return $this->success('Invitation sent successfully', (object) [], 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function blockChild(int $child_id)
    {

        try {
            $user = Auth::guard('api')->user();

            $child = AthleteProfiles::query()
                ->where('id', $child_id)
                ->where('parent_id', $user->id)
                ->first();

            if (! $child) {
                return response()->json([
                    'status' => false,
                    'message' => 'Child not found',
                ], 200);
            }

            $child->is_blocked = true;
            $child->save();
            $childuser = User::find($child->user_id);
            if ($childuser) {
                $childuser->status = 'block';
                $childuser->save();
            }
            return $this->success([], 'Child blocked successfully', 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function unblockChild(int $child_id)
    {
        try {
            $user = Auth::guard('api')->user();

            $child = AthleteProfiles::query()
                ->where('id', $child_id)
                ->where('parent_id', $user->id)
                ->first();

            if (! $child) {
                return response()->json([
                    'status' => false,
                    'message' => 'Child not found',
                ], 200);
            }

            $child->is_blocked = false;
            $child->save();
            $childuser = User::find($child->user_id);
            if ($childuser) {
                $childuser->status = 'approve';
                $childuser->save();
            }
            return $this->success([], 'Child unblocked successfully', 200);
        } catch (\Exception $e) {
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

    private function canViewAthleteProfile($viewer, AthleteProfiles $profile): bool
    {
        if (! $viewer) {
            return false;
        }

        $privacy = strtolower((string) ($profile->privacy_settings ?: 'public'));

        if ($privacy === 'public') {
            return true;
        }

        if ($privacy === 'only_player') {
            if (! in_array($viewer->role, ['parent', 'player'], true)) {
                return false;
            }

            $viewerParentId = $viewer->role === 'parent'
                ? $viewer->id
                : AthleteProfiles::query()->where('user_id', $viewer->id)->value('parent_id');

            return ! is_null($viewerParentId)
                && ! is_null($profile->parent_id)
                && (int) $viewerParentId === (int) $profile->parent_id;
        }

        if ($privacy === 'coach_and_team') {
            if ($viewer->role !== 'coach') {
                return false;
            }

            $viewerCoachClubIds = TeamPlayer::query()
                ->where('coach_id', $viewer->id)
                ->whereNotNull('club_id')
                ->pluck('club_id')
                ->unique()
                ->values()
                ->all();

            if (empty($viewerCoachClubIds)) {
                return false;
            }

            $targetClubIds = TeamPlayer::query()
                ->whereNotNull('club_id')
                ->where(function ($query) use ($profile) {
                    $query->where('child_id', $profile->id);

                    if (! is_null($profile->user_id)) {
                        $query->orWhere('player_id', $profile->user_id);
                    }
                })
                ->pluck('club_id')
                ->unique()
                ->values()
                ->all();

            if (empty($targetClubIds)) {
                return false;
            }

            return count(array_intersect($viewerCoachClubIds, $targetClubIds)) > 0;
        }

        return false;
    }
}