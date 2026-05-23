<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\ClubOrganization;
use App\Models\ClubProfile;
use App\Models\Coach;
use App\Models\Endorse;
use App\Models\PlayerVotingSyatem;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PublicProfileController extends Controller
{
    use ApiResponse;

    public function athleteProfileById($athlete_id)
    {
        try {
            $athleteId = $this->normalizeRouteId($athlete_id);
            if ($athleteId === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid athlete id',
                    'data' => [],
                ], 200);
            }

            $profileQuery = AthleteProfiles::query()
                ->with([
                    'strengths',
                    'mediaReels',
                    'mediaLinks',
                    'achievements',
                    'primaryPosition:id,name',
                    'secondaryPosition:id,name',
                    'sportOption:id,name',
                    'seasonStats',
                ]);

            $profile = (clone $profileQuery)->whereKey($athleteId)->first();

            if (! $profile) {
                return response()->json([

                    'message' => 'Athlete profile not found',
                    'data' => [],
                ], 200);
            }

            $privacy = strtolower((string) ($profile->privacy_settings ?: 'public'));
            $authUser = Auth::guard('api')->user();
            if ($privacy !== 'public') {
                if (! $authUser || $authUser->id !== $profile->user_id) {
                    return $this->forbidden([], 'This athlete profile is private.', 403);
                }
            }

            $lastFiveSeasons = $profile->seasonStats
                ->sortByDesc('season_year')
                ->take(5)
                ->values()
                ->map(function ($seasonStat) {
                    return [
                        'season_year' => (int) $seasonStat->season_year,
                        'total_played_games' => (int) $seasonStat->total_played_games,
                        'total_played_time' => (int) $seasonStat->total_played_time,
                        'goals' => (int) $seasonStat->goals,
                        'assist' => (int) $seasonStat->assist,
                        'yellow_cards' => (int) $seasonStat->yellow_cards,
                        'red_cards' => (int) $seasonStat->red_cards,
                        'clean_sheets' => (int) $seasonStat->clean_sheets,
                        'total_saves' => (int) $seasonStat->total_saves,
                        'penalty_saves' => (int) $seasonStat->penalty_saves,
                    ];
                });

            $strengthsWithEndorse = $profile->strengths->map(function ($strength) {
                $endorseCount = Endorse::query()
                    ->where('player_strength_id', $strength->id)
                    ->count();

                return [
                    'id' => $strength->id,
                    'strength_type' => $strength->strength_type,
                    'strength_name' => $strength->strength_name,
                    'endorse_count' => $endorseCount,
                    'endorsed' => $endorseCount > 0,
                ];
            })->values();

            $achievements = $profile->achievements->map(function ($achievement) {
                return [
                    'id' => $achievement->id,
                    'title' => $achievement->title,
                    'description' => $achievement->description,
                    'date_earned' => $achievement->date_earned,
                    'image' => $achievement->image ? asset($achievement->image) : null,
                ];
            })->values();

            $gallery = $profile->mediaReels
                ->where('status', 'image')
                ->values()
                ->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'image' => $media->image ? asset($media->image) : null,
                        'uploaded_at' => $media->created_at,
                    ];
                });

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

            $mediaLinks = $profile->mediaLinks->map(function ($link) {
                return [
                    'id' => $link->id,
                    'link' => $link->link,
                    'status' => $link->status,
                    'image' => $link->image ? asset($link->image) : null,
                ];
            })->values();

            $basicInfo = [
                'id' => $profile->id,
                'name' => $profile->name,
                'last_name' => $profile->last_name,
                'full_name' => trim(($profile->name ?? '') . ' ' . ($profile->last_name ?? '')),
                'dob' => $profile->dob,
                'age' => $profile->dob ? Carbon::parse($profile->dob)->age : null,
                'gender' => $profile->gender,
                'nationality' => $profile->nationality,
                'city' => $profile->city,
                'country' => $profile->country,
                'email' => $profile->email,
                'image' => $profile->image ? asset($profile->image) : null,
                'biography' => $profile->athlete_biography,
                'privacy_settings' => $profile->privacy_settings,
                'sports' => $profile->sports,
                'preview' => $profile->preview ? asset($profile->preview) : null,
                'sport_option_id' => $profile->sport_option_id,
                'sport_option' => $profile->sportOption
                    ? ['id' => (int) $profile->sportOption->id, 'name' => $profile->sportOption->name]
                    : null,
            ];

            $positionInfo = [
                'primary_position' => $profile->primaryPosition
                    ? ['id' => (int) $profile->primaryPosition->id, 'name' => $profile->primaryPosition->name]
                    : null,
                'secondary_position' => $profile->secondaryPosition
                    ? ['id' => (int) $profile->secondaryPosition->id, 'name' => $profile->secondaryPosition->name]
                    : null,
                'jersey_number' => $profile->jersey_number,
                'dominant_foot' => $profile->dominant_foot,
                'club_team' => $profile->club_team,
                'sports_selection' => $profile->sports_selection,
            ];

            $playerStats = [
                'total_matches' => (int) ($profile->total_played_games ?? 0),
                'total_played_time' => (int) ($profile->total_played_time ?? 0),
                'goals' => (int) ($profile->goals ?? 0),
                'assists' => (int) ($profile->assist ?? 0),
                'yellow_cards' => (int) ($profile->yellow_cards ?? 0),
                'red_cards' => (int) ($profile->red_cards ?? 0),
                'clean_sheets' => (int) ($profile->clean_sheets ?? 0),
                'total_saves' => (int) ($profile->total_saves ?? 0),
            ];

            $provencialCount = PlayerVotingSyatem::query()
                ->where('vote_for_player_id', $profile->id)
                ->where('vote_type', 'provencial')
                ->count();

            $professionalCount = PlayerVotingSyatem::query()
                ->where('vote_for_player_id', $profile->id)
                ->where('vote_type', 'professional')
                ->count();

            $data = [
                'basic_info' => $basicInfo,
                'position_info' => $positionInfo,
                'player_stats' => $playerStats,
                'strengths' => $strengthsWithEndorse,
                'achievements' => $achievements,
                'gallery' => $gallery,
                'videos' => $reels,
                'media_links' => $mediaLinks,
                'season_stats_last_five_years' => $lastFiveSeasons,
                'provencial_votes' => $provencialCount,
                'professional_votes' => $professionalCount,
            ];

            return $this->success($data, 'Athlete public profile fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function coachProfileById($coach_id)
    {
        try {
            $coachId = $this->normalizeRouteId($coach_id);
            if ($coachId === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid coach id',
                    'data' => [],
                ], 200);
            }

            $coachQuery = Coach::query()
                ->with([
                    'coachingTitles:id,coach_id,title',
                    'media:id,coach_id,image',
                    'currentPosition:id,name',
                    'sportOption:id,name',
                ])
                ->withAvg('programReviews', 'rating')
                ->withCount('programReviews');

            $coach = (clone $coachQuery)->whereKey($coachId)->first();

            if (! $coach) {
                return response()->json([

                    'message' => 'Coach profile not found',
                    'data' => [],
                ], 200);
            }

            if ($coach->status !== 'approve') {
                return $this->forbidden([], 'This coach profile is not public yet.', 403);
            }

            $privacy = strtolower((string) ($coach->privacy_settings ?: 'public'));
            if ($privacy !== 'public') {
                return $this->forbidden([], 'This coach profile is private.', 403);
            }

            $overallAvgRating = $coach->visible_reviews
                ? round((float) ($coach->program_reviews_avg_rating ?? 0), 2)
                : null;

            $data = [
                'coach_id' => $coach->id,
                'visibility' => $coach->status === 'approve' ? 'public' : 'pending',
                'profile' => [
                    'name' => trim(($coach->name ?? '') . ' ' . ($coach->last_name ?? '')),
                    'dob' => $coach->dob,
                    'age' => $coach->dob ? Carbon::parse($coach->dob)->age : null,
                    'gender' => $coach->gender,
                    'sport_option_id' => $coach->sport_option_id,
                    'sport_option' => $coach->sportOption
                        ? ['id' => (int) $coach->sportOption->id, 'name' => $coach->sportOption->name]
                        : null,
                    'sports' => $coach->sports,
                    'email' => $coach->email,
                    'nationality' => $coach->nationality,
                    'city_id' => $coach->city_id,
                    'country_id' => $coach->country_id,
                    'city' => $coach->city,
                    'country' => $coach->country,
                    'profile_image' => $coach->coach_profile_pic ? asset($coach->coach_profile_pic) : null,
                    'bio' => $coach->coaching_philosophy,
                    'facebook_link' => $coach->facebook_link,
                    'twitter_link' => $coach->twitter_link,
                    'instagram_link' => $coach->instagram_link,
                    'tiktok_link' => $coach->tiktok_link,
                    'whatsapp_link' => $coach->whatsapp_link,
                    'current_role' => $coach->currentPosition
                        ? ['id' => (int) $coach->currentPosition->id, 'name' => $coach->currentPosition->name]
                        : null,
                    'years_of_experience' => $coach->years_of_experience,
                    'highest_education' => $coach->highest_education,
                    'coaching_education' => $coach->coaching_education,
                    'player_centric_approach' => (bool) $coach->player_centric_approach,
                    'data_driving_training' => (bool) $coach->data_driving_training,
                    'visible_reviews' => (bool) $coach->visible_reviews,
                    'allow_parent_player_reviews' => (bool) $coach->allow_parent_player_reviews,
                    'overall_avg_rating' => $overallAvgRating,
                    'total_reviews' => $coach->visible_reviews ? (int) $coach->program_reviews_count : 0,
                    'preview' => $coach->preview ? asset($coach->preview) : null,
                ],
                'coaching_titles' => $coach->coachingTitles
                    ->pluck('title')
                    ->filter()
                    ->values(),
                'coach_media' => $coach->media
                    ->map(fn($item) => [
                        'id' => $item->id,
                        'image' => $item->image ? asset($item->image) : null,
                    ])
                    ->values(),
                'experience_education' => [
                    [
                        'title' => $coach->currentPosition?->name,
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

            return $this->success($data, 'Coach public profile fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function clubProfileById($club_id)
    {
        try {
            $clubId = $this->normalizeRouteId($club_id);
            if ($clubId === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid club id',
                    'data' => [],
                ], 200);
            }

            $clubProfileQuery = ClubProfile::query()
                ->with('sportOption:id,name');

            $clubProfile = (clone $clubProfileQuery)->whereKey($clubId)->first();

            if (! $clubProfile) {
                return response()->json([

                    'message' => 'Club profile not found',
                    'data' => [],
                ], 200);
            }

            $privacy = strtolower((string) ($clubProfile->privacy_settings ?: 'public'));
            if ($privacy !== 'public') {
                return $this->forbidden([], 'This club profile is private.', 403);
            }

            $clubOrganizations = ClubOrganization::query()
                ->with('organizationType:id,name,status')
                ->where('user_id', $clubProfile->user_id)
                ->get();

            $organizationTypes = $clubOrganizations
                ->filter(fn($item) => $item->organizationType !== null)
                ->map(function ($item) {
                    return [
                        'id' => (int) $item->organizationType->id,
                        'name' => $item->organizationType->name,
                        'status' => $item->organizationType->status,
                    ];
                })
                ->values();

            $data = [
                'id' => $clubProfile->id,
                'club_name' => $clubProfile->club_name,
                'city' => $clubProfile->city,
                'city_id' => $clubProfile->city_id,
                'state' => $clubProfile->state,
                'country' => $clubProfile->country,
                'country_id' => $clubProfile->country_id,
                'phone' => $clubProfile->phone,
                'email' => $clubProfile->email,
                'club_description' => $clubProfile->club_description,
                'sport_option_id' => $clubProfile->sport_option_id,
                'sport_option' => $clubProfile->sportOption
                    ? ['id' => (int) $clubProfile->sportOption->id, 'name' => $clubProfile->sportOption->name]
                    : null,
                'sports_name' => $clubProfile->sports_name,
                'privacy_settings' => $clubProfile->privacy_settings,
                'club_logo_url' => $clubProfile->club_logo ? url($clubProfile->club_logo) : null,
                'facebook_link' => $clubProfile->facebook_link,
                'twitter_link' => $clubProfile->twitter_link,
                'instagram_link' => $clubProfile->instagram_link,
                'tiktok_link' => $clubProfile->tiktok_link,
                'whatsapp_link' => $clubProfile->whatsapp_link,
                'preview' => $clubProfile->preview ? asset($clubProfile->preview) : null,
                'organization_types' => $organizationTypes,
            ];

            return $this->success($data, 'Club public profile fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    private function normalizeRouteId($id): ?int
    {
        if (is_int($id)) {
            return $id > 0 ? $id : null;
        }

        if (! is_string($id)) {
            return null;
        }

        $value = trim($id);
        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }
}