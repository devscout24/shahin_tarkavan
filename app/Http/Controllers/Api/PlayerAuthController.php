<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\PlayerAchievement;
use App\Models\PlayerMediaLink;
use App\Models\PlayerMediaVideo;
use App\Models\PlayerPosition;
use App\Models\PlayerSeasonStat;
use App\Models\PlayerStrength;
use App\Models\City;
use App\Models\Country;
use App\Models\SportOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Traits\ApiResponse;

class PlayerAuthController extends Controller
{
    use ApiResponse;
    public function PlayerRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'dob' => 'required|date|before_or_equal:' . now()->subYears(18)->format('Y-m-d'),
            'nationality' => 'required',
            'country_id' => 'nullable|integer|exists:countries,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'season_year' => 'nullable|integer|min:1900|max:2100',
            'season_stats' => 'nullable|array|max:5',
            'season_stats.*.season_year' => 'required|integer|min:1900|max:2100',
            'season_stats.*.total_played_games' => 'nullable|integer|min:0',
            'season_stats.*.total_played_time' => 'nullable|integer|min:0',
            'season_stats.*.goals' => 'nullable|integer|min:0',
            'season_stats.*.assist' => 'nullable|integer|min:0',
            'season_stats.*.yellow_cards' => 'nullable|integer|min:0',
            'season_stats.*.red_cards' => 'nullable|integer|min:0',
            'season_stats.*.clean_sheets' => 'nullable|integer|min:0',
            'season_stats.*.total_saves' => 'nullable|integer|min:0',
            'season_stats.*.penalty_saves' => 'nullable|integer|min:0',
            'sport_option_id' => [
                'nullable',
                'integer',
                Rule::exists('sport_options', 'id')->where(function ($query): void {
                    $query->where('audience', 'player')->where('status', 'active');
                }),
            ],
            'sports_selection' => 'nullable|string|max:255',
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
            $user->status = 'approve';
            $user->save();


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

                    return $this->validationError(['city' => ['Selected city does not belong to selected country.']], 422);
                }
            }

            // Ensure a single player profile per user: create on first, update afterwards
            $existingProfile = AthleteProfiles::query()->where('user_id', $user->id)->first();

            $name = $request->name;
            $lastName = $request->last_name ?? null;
            $dob = $request->dob;
            $gender = $request->gender;
            $nationality = $request->nationality;
            $sportOptionId = SportOption::resolveIdForAudience('player', $request->input('sport_option_id', $request->input('sports_selection')));
            $sportOptionName = $sportOptionId ? SportOption::query()->where('id', $sportOptionId)->value('name') : null;

            $email = $request->email ?? null;
            $jerseyNumber = $request->jersey_number ?? null;
            $dominantFoot = $request->dominant_foot ?? null;
            $clubTeam = $request->club_team ?? null;
            $cityVal = $cityName ?? ($request->city ?? null);
            $countryVal = $countryName ?? ($request->country ?? null);

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

            $imagePath = null;
            if ($request->hasFile('profile_image')) {
                if ($existingProfile && $existingProfile->image && file_exists(public_path($existingProfile->image))) {
                    unlink(public_path($existingProfile->image));
                }

                $file = $request->file('profile_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = 'uploads/athlete_profiles/';
                $imagePath = $path . $filename;
                $file->move(public_path($path), $filename);
            }

            $child = AthleteProfiles::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $name,
                    'last_name' => $lastName,
                    'dob' => $dob,
                    'gender' => $gender,
                    'nationality' => $nationality,
                    'email' => $email,
                    'sport_option_id' => $sportOptionId,
                    'sports' => $sportOptionName ?? $request->input('sports_selection'),
                    'sports_selection' => $sportOptionName ?? $request->input('sports_selection'),
                    'jersey_number' => $jerseyNumber,
                    'dominant_foot' => $dominantFoot,
                    'club_team' => $clubTeam,
                    'country_id' => $countryId,
                    'city_id' => $cityId,
                    'city' => $cityVal,
                    'country' => $countryVal,
                    'user_id' => $user->id,

                    'primary_position' => $primaryPositionId,
                    'secondary_position' => $secondaryPositionId,
                    'athlete_biography' => $request->athlete_biography ?? null,
                    'privacy_settings' => $request->privacy_settings ?? null,
                    'total_played_games' => $request->total_played_games ?? 0,
                    'total_played_time' => $request->total_played_time ?? 0,
                    'goals' => $request->goals ?? 0,
                    'assist' => $request->assist ?? 0,
                    'yellow_cards' => $request->yellow_cards ?? 0,
                    'red_cards' => $request->red_cards ?? 0,
                    'clean_sheets' => $request->clean_sheets ?? 0,
                    'total_saves' => $request->total_saves ?? 0,
                    'image' => $imagePath ?? ($existingProfile->image ?? null),
                ]
            );

            $this->upsertSeasonStatsFromRequest($request, $child);
            $this->syncAthleteMainStatsFromLatestSeason($child);

            if ($request->has('strengths')) {
                $strengths = array_slice((array) $request->strengths, 0, 7);
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
            'dob' => 'sometimes|required|date',
            'nationality' => 'sometimes|required',
            'country_id' => 'nullable|integer|exists:countries,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'season_year' => 'nullable|integer|min:1900|max:2100',
            'season_stats' => 'nullable|array|max:5',
            'season_stats.*.season_year' => 'required|integer|min:1900|max:2100',
            'season_stats.*.total_played_games' => 'nullable|integer|min:0',
            'season_stats.*.total_played_time' => 'nullable|integer|min:0',
            'season_stats.*.goals' => 'nullable|integer|min:0',
            'season_stats.*.assist' => 'nullable|integer|min:0',
            'season_stats.*.yellow_cards' => 'nullable|integer|min:0',
            'season_stats.*.red_cards' => 'nullable|integer|min:0',
            'season_stats.*.clean_sheets' => 'nullable|integer|min:0',
            'season_stats.*.total_saves' => 'nullable|integer|min:0',
            'season_stats.*.penalty_saves' => 'nullable|integer|min:0',
            'sport_option_id' => [
                'nullable',

            ],
            'sports_selection' => 'nullable|string|max:255',
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
            $user->status = 'approve';
            $user->save();

            $activeChildId = $request->header('active-child-id') ?? $request->get('active_child_id');

            $child = AthleteProfiles::query()
                ->when($activeChildId, fn($q) => $q->where('id', $activeChildId))
                ->when(!$activeChildId, fn($q) => $q->where('user_id', $user->id))
                ->first();

            if (! $child) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Player not found',
                ], 404);
            }

            $countryId = $request->has('country_id')
                ? ($request->filled('country_id') ? (int) $request->input('country_id') : null)
                : $child->country_id;
            $cityId = $request->has('city_id')
                ? ($request->filled('city_id') ? (int) $request->input('city_id') : null)
                : $child->city_id;

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

                    return $this->validationError(['city_id' => ['Selected city does not belong to selected country.']], 422);
                }
            }

            $countryName = $countryId ? Country::query()->where('id', $countryId)->value('name') : ($request->country ?? $child->country);
            $cityName = $cityId ? City::query()->where('id', $cityId)->value('name') : ($request->city ?? $child->city);

            $child->name = $request->name ?? $child->name;
            $child->last_name = $request->last_name ?? $child->last_name;
            $child->dob = $request->dob ?? $child->dob;
            $child->gender = $request->gender ?? $child->gender;
            $child->nationality = $request->nationality ?? $child->nationality;
            $sportOptionId = $request->filled('sport_option_id')
                ? SportOption::resolveIdForAudience('player', $request->input('sport_option_id'))
                : SportOption::resolveIdForAudience('player', $request->input('sports_selection'));
            $sportOptionName = $sportOptionId ? SportOption::query()->where('id', $sportOptionId)->value('name') : ($request->input('sports_selection') ?? $child->sports);

            $child->email = $request->email ?? $child->email;
            $child->sport_option_id = $sportOptionId ?? $child->sport_option_id;
            $child->sports = $sportOptionName ?? $child->sports;
            $child->sports_selection = $sportOptionName ?? $child->sports_selection;
            $child->jersey_number = $request->jersey_number ?? $child->jersey_number;
            $child->dominant_foot = $request->dominant_foot ?? $child->dominant_foot;
            $child->club_team = $request->club_team ?? $child->club_team;

            $child->facebook_link = $request->facebook_link ?? $child->facebook_link;
            $child->twitter_link = $request->twitter_link ?? $child->twitter_link;
            $child->instagram_link = $request->instagram_link ?? $child->instagram_link;
            $child->tiktok_link = $request->tiktok_link ?? $child->tiktok_link;
            $child->whatsapp_link = $request->whatsapp_link ?? $child->whatsapp_link;


            $child->country_id = $countryId;
            $child->city_id = $cityId;
            $child->city = $cityName;
            $child->country = $countryName;
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
            $child->total_played_time = $request->total_played_time ?? $child->total_played_time;
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

            $this->upsertSeasonStatsFromRequest($request, $child);
            $this->syncAthleteMainStatsFromLatestSeason($child);

            if ($request->has('strengths')) {

                $strengths = array_slice((array) $request->strengths, 0, 7);


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
                $titles = array_values(array_filter((array) $request->title));

                PlayerAchievement::where('player_id', $child->id)
                    ->whereNotIn('title', $titles)
                    ->delete();

                foreach ((array) $request->title as $index => $title) {
                    if (empty($title)) {
                        continue;
                    }

                    $data = [
                        'description' => $request->description[$index] ?? null,
                        'date_earned' => $request->date_earned[$index] ?? null,
                    ];

                    $achievement = PlayerAchievement::updateOrCreate(
                        [
                            'player_id' => $child->id,
                            'title' => $title,
                        ],
                        $data
                    );

                    if (isset($request->image[$index]) && $request->hasFile("image.$index")) {
                        if ($achievement->image && file_exists(public_path($achievement->image))) {
                            @unlink(public_path($achievement->image));
                        }
                        $file = $request->file("image.$index");
                        $filename = time() . '_' . $file->getClientOriginalName();
                        $path = 'uploads/achievements/';
                        $filePath = $path . $filename;
                        $file->move(public_path($path), $filename);
                        $achievement->image = $filePath;
                        $achievement->save();
                    }
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

    private function upsertSeasonStatsFromRequest(Request $request, AthleteProfiles $profile): void
    {
        $seasonStats = collect($request->input('season_stats', []));

        if ($seasonStats->isEmpty()) {
            $hasLegacyStats = $request->hasAny([
                'total_played_games',
                'total_played_time',
                'goals',
                'assist',
                'yellow_cards',
                'red_cards',
                'clean_sheets',
                'total_saves',
                'penalty_saves',
            ]);

            if ($hasLegacyStats) {
                $seasonStats = collect([[
                    'season_year' => (int) ($request->input('season_year') ?: now()->year),
                    'total_played_games' => (int) $request->input('total_played_games', 0),
                    'total_played_time' => (int) $request->input('total_played_time', 0),
                    'goals' => (int) $request->input('goals', 0),
                    'assist' => (int) $request->input('assist', 0),
                    'yellow_cards' => (int) $request->input('yellow_cards', 0),
                    'red_cards' => (int) $request->input('red_cards', 0),
                    'clean_sheets' => (int) $request->input('clean_sheets', 0),
                    'total_saves' => (int) $request->input('total_saves', 0),
                    'penalty_saves' => (int) $request->input('penalty_saves', 0),
                ]]);
            }
        }

        foreach ($seasonStats as $seasonRow) {
            $seasonYear = (int) ($seasonRow['season_year'] ?? 0);
            if ($seasonYear < 1900 || $seasonYear > 2100) {
                continue;
            }

            PlayerSeasonStat::query()->updateOrCreate(
                [
                    'athlete_profile_id' => $profile->id,
                    'season_year' => $seasonYear,
                ],
                [
                    'total_played_games' => (int) ($seasonRow['total_played_games'] ?? 0),
                    'total_played_time' => (int) ($seasonRow['total_played_time'] ?? 0),
                    'goals' => (int) ($seasonRow['goals'] ?? 0),
                    'assist' => (int) ($seasonRow['assist'] ?? 0),
                    'yellow_cards' => (int) ($seasonRow['yellow_cards'] ?? 0),
                    'red_cards' => (int) ($seasonRow['red_cards'] ?? 0),
                    'clean_sheets' => (int) ($seasonRow['clean_sheets'] ?? 0),
                    'total_saves' => (int) ($seasonRow['total_saves'] ?? 0),
                    'penalty_saves' => (int) ($seasonRow['penalty_saves'] ?? 0),
                ]
            );
        }
    }

    private function syncAthleteMainStatsFromLatestSeason(AthleteProfiles $profile): void
    {
        $latestSeason = $profile->seasonStats()->orderByDesc('season_year')->orderByDesc('id')->first();
        if (! $latestSeason) {
            return;
        }

        $profile->total_played_games = (int) $latestSeason->total_played_games;
        $profile->total_played_time = (int) $latestSeason->total_played_time;
        $profile->goals = (int) $latestSeason->goals;
        $profile->assist = (int) $latestSeason->assist;
        $profile->yellow_cards = (int) $latestSeason->yellow_cards;
        $profile->red_cards = (int) $latestSeason->red_cards;
        $profile->clean_sheets = (int) $latestSeason->clean_sheets;
        $profile->total_saves = (int) $latestSeason->total_saves;
        $profile->save();
    }
}