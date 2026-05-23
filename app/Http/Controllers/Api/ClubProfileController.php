<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ClubOrganization;
use App\Models\ClubProfile;
use App\Models\Country;
use App\Models\OrganizationType;
use App\Traits\ApiResponse;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClubProfileController extends Controller
{
    use ApiResponse;
    public function AddUpdateClubProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'club_name' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'city_id' => 'nullable|exists:cities,id',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'country' => 'nullable|string',
            'city' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'club_description' => 'nullable|string',
            'sport_option_id' => 'nullable|integer|exists:sport_options,id',
            'sports_name' => 'nullable|string|max:255',
            'club_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'privacy_settings' => 'nullable|in:public,private,players,coach_and_players',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        $user = Auth::guard('api')->user();
        $user->status = 'approve';
        $user->save();
        if (! $user) {
            return $this->forbidden([], 'Authentication required.', 401);
        }

        $existingProfile = ClubProfile::query()->where('user_id', $user?->id)->first() ?? null;

        try {

            DB::beginTransaction();

            $countryName = $request->filled('country') ? trim($request->input('country')) : $request->input('country');
            $cityName = $request->filled('city') ? trim($request->input('city')) : $request->input('city');

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


            $clubLogoPath = null;

            if ($request->hasFile('club_logo')) {

                if ($existingProfile && $existingProfile->club_logo && file_exists(public_path($existingProfile->club_logo))) {
                    unlink(public_path($existingProfile->club_logo));
                }

                $file = $request->file('club_logo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = 'uploads/club_logos/';
                $file->move(public_path($path), $filename);
                $clubLogoPath = $path . $filename;
            }

            $sportOptionId = $request->filled('sport_option_id')
                ? (int) $request->input('sport_option_id')
                : null;
            $sportOptionName = $sportOptionId
                ? \App\Models\SportOption::query()->where('id', $sportOptionId)->value('name')
                : $request->input('sports_name');

            if ($existingProfile) {
                $existingProfile->update([
                    'club_name' => $request->input('club_name'),
                    'city' => $cityName,
                    'city_id' => $cityId,
                    'state' => $request->input('state'),
                    'country' => $countryName,
                    'country_id' => $countryId,
                    'phone' => $request->input('phone'),

                    'club_description' => $request->input('club_description'),
                    'sport_option_id' => $sportOptionId ?? $existingProfile->sport_option_id,
                    'sports' => $sportOptionName ?? $existingProfile->sports,
                    'privacy_settings' => $request->input('privacy_settings', $existingProfile->privacy_settings ?? 'public'),
                    'club_logo' => $clubLogoPath ?? $existingProfile->club_logo,
                    'facebook_link' => $request->facebook_link ?? $existingProfile->facebook_link,
                    'twitter_link' => $request->twitter_link ?? $existingProfile->twitter_link,
                    'instagram_link' => $request->instagram_link ?? $existingProfile->instagram_link,
                    'tiktok_link' => $request->tiktok_link ?? $existingProfile->tiktok_link,
                    'whatsapp_link' => $request->whatsapp_link ?? $existingProfile->whatsapp_link
                ]);
            } else {
                $existingProfile = ClubProfile::create([
                    'user_id' => $user->id,
                    'club_name' => $request->input('club_name'),
                    'city' => $cityName,
                    'city_id' => $cityId,
                    'state' => $request->input('state'),
                    'country' => $countryName,
                    'country_id' => $countryId,
                    'phone' => $request->input('phone'),

                    'club_description' => $request->input('club_description'),
                    'sport_option_id' => $sportOptionId,
                    'sports' => $sportOptionName,
                    'privacy_settings' => $request->input('privacy_settings', 'public'),
                    'club_logo' => $clubLogoPath,
                ]);
            }

            if ($request->has('organization_type_id')) {

                $organizationTypeIds = (array) $request->input('organization_type_id');

                foreach ($organizationTypeIds as $organizationTypeId) {

                    $organizationType = OrganizationType::query()->find($organizationTypeId);

                    if ($organizationType) {
                        ClubOrganization::query()->updateOrCreate(
                            [
                                'user_id' => $user->id,
                                'organization_type_id' => $organizationTypeId
                            ],
                            [
                                'organization_type_id' => $organizationTypeId
                            ]
                        );
                    }
                }
            }
            DB::commit();

            return $this->success($existingProfile, 'Club profile saved successfully', 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Club profile save failed', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return $this->error('An error occurred while saving the club profile', 500);
        }
    }

    public function getClubProfile(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return $this->forbidden([], 'Authentication required.', 401);
            }

            $clubProfile = ClubProfile::query()->where('user_id', $user->id)->first();

            if (! $clubProfile) {
                return $this->notFound([], 'Club profile not found.', 404);
            }

            $clubOrganizations = ClubOrganization::query()
                ->with('organizationType:id,name,status')
                ->where('user_id', $user->id)
                ->get();

            $organizationTypes = $clubOrganizations
                ->filter(fn($item) => $item->organizationType !== null)
                ->map(function ($item) {
                    return [
                        'id' => $item->organizationType->id,
                        'name' => $item->organizationType->name,
                        'status' => $item->organizationType->status,
                    ];
                })
                ->values();

            $data = [
                'id' => $clubProfile->id,
                'club_name' => $clubProfile->club_name,

                'email' => $user->email,
                'profile_email' => $clubProfile->email,
                'city' => $clubProfile->city,
                'city_id' => $clubProfile->city_id,
                'state' => $clubProfile->state,
                'country' => $clubProfile->country,
                'country_id' => $clubProfile->country_id,
                'phone' => $clubProfile->phone,
                'club_description' => $clubProfile->club_description,
                'sport_option_id' => $clubProfile->sport_option_id,
                'sport_option' => $clubProfile->sportOption
                    ? ['id' => (int) $clubProfile->sportOption->id, 'name' => $clubProfile->sportOption->name]
                    : null,
                'sports_name' => $clubProfile->sports_name,
                'privacy_settings' => $clubProfile->privacy_settings,
                'club_logo_url' => $clubProfile->club_logo ? url($clubProfile->club_logo) : null,

                'organization_types' => $organizationTypes,

            ];

            return $this->success($data, 'Club profile fetched successfully.', 200);
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching the club profile.', 500);
        }
    }

    public function deleteClubMedia($media_id)
    {
        // Logic to delete club media
    }

    public function editdata(Request $request)
    {
        // Logic to get data for editing club profile
    }

    public function getOrganizationTypes()
    {
        try {
            $organizationTypes = OrganizationType::query()->where('status', 'active')->get();

            return response()->json([
                'status' => true,
                'message' => 'Organization types fetched successfully',
                'data' => $organizationTypes,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
