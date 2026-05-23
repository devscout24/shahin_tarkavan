<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\City;
use App\Models\ClubProfile;
use App\Models\Coach;
use App\Models\Country;
use App\Models\ParentAggrements;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

use function Laravel\Prompts\password;

class ParentController extends Controller
{

    use ApiResponse;


    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required',
            'country' => 'nullable|string',
            'city' => 'nullable|string',
            'date_of_birth' => 'required_if:role,player|date',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 422);
        }


        if ($request->role == 'player') {
            $age = Carbon::parse($request->date_of_birth)->age;

            if ($age < 18) {
                return response()->json([
                    'status' => false,
                    'message' => 'Age must be at least 18 years. You can create an account under a parent.'
                ], 422);
            }
        }

        try {
            $countryName = $request->filled('country') ? trim($request->input('country')) : null;
            $cityName = $request->filled('city') ? trim($request->input('city')) : null;

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
                    return $this->validationError(['city' => ['Selected city does not belong to selected country.']], 422);
                }
            }

            $user = new User();
            $user->name = $request->name;
            $user->last_name = $request->last_name ?? null;
            $user->email = $request->email;
            $user->password = bcrypt($request->password); // ⚠️ important (hash)
            $user->role = $request->role;
            $user->country_id = $countryId;
            $user->city_id = $cityId;
            if ($request->role == 'coach' || $request->role == 'club' || $request->role == 'player') {
                $user->status = 'pending';
            } else {
                $user->status = 'approve';
            }

            $user->is_verified = true;
            $user->save();

            $user->assignRole(
                \Spatie\Permission\Models\Role::query()
                    ->where('name', 'parent')
                    ->where('guard_name', 'api')
                    ->first()
            );

            $token = (string) JWTAuth::fromUser($user);

            return $this->success(
                ['user' => $user, 'token' => $token],
                'Parent registered successfully',
                200
            );
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function aggrement(Request $request)
    {

        $validator = Validator::make($request->all(), [

            'is_agree' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 422);
        }

        try {
            $user = Auth::guard('api')->user();
            $aggrement = new ParentAggrements();
            $aggrement->user_id = $user->id;
            $aggrement->agreed = $request->is_agree;
            $aggrement->save();

            $data =  [
                'user_id' => $user->id,
                'is_agree' => $request->is_agree,
                'user_name' => $user->name,
            ];

            return $this->success($data, 'Aggrement updated successfully', 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function getProfile(Request $request)
    {
        try {
            $user = Auth::guard('api')->user()->loadMissing(['country:id,name', 'city:id,name,country_id']);
            $privacy_settings = null;
            $visible_reviews = true;
            $allow_parent_player_reviews = true;

            if ($user->role == 'player') {
                $privacy_settings = AthleteProfiles::where('user_id', $user->id)->value('privacy_settings');
            } elseif ($user->role == 'coach') {
                $coach = Coach::where('user_id', $user->id)->first();
                if ($coach) {
                    $privacy_settings = $coach->privacy_settings;
                    $visible_reviews = (bool) $coach->visible_reviews;
                    $allow_parent_player_reviews = (bool) $coach->allow_parent_player_reviews;
                }
            } elseif ($user->role == 'club') {
                $privacy_settings = ClubProfile::where('user_id', $user->id)->value('privacy_settings');
            }

            $data = [
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' => $user->profile_image ? asset($user->profile_image) : null,
                'country_id' => $user->country_id,
                'city_id' => $user->city_id,
                'country' => $user->country?->name,
                'city' => $user->city?->name,
                'privacy_settings' => $privacy_settings ?? null,
            ];

            if ($user->role == 'coach') {
                $data['visible_reviews'] = $visible_reviews;
                $data['allow_parent_player_reviews'] = $allow_parent_player_reviews;
            }

            return $this->success($data, 'Parent profile retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function UpdateParentProfile(Request $request)
    {
        try {

            // =========================
            // VALIDATION
            // =========================
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'country' => 'nullable|string',
                'city' => 'nullable|string',
                'privacy_settings' => 'nullable',
                'current_password' => 'nullable|string',
                'new_password' => 'nullable|string|min:8|confirmed',
                'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            if ($validator->fails()) {
                return $this->validationError([], $validator->errors(), 422);
            }

            $user = Auth::guard('api')->user();

            // =========================
            // COUNTRY
            // =========================
            $countryId = $user->country_id;

            if ($request->filled('country')) {
                $countryId = Country::whereRaw('LOWER(name) = ?', [strtolower(trim($request->country))])
                    ->value('id');
            }

            // =========================
            // CITY
            // =========================
            $cityId = $user->city_id;

            if ($request->filled('city')) {
                $cityId = City::whereRaw('LOWER(name) = ?', [strtolower(trim($request->city))])
                    ->value('id');
            }

            if ($cityId && !$countryId) {
                $countryId = City::where('id', $cityId)->value('country_id');
            }

            if ($cityId && $countryId) {
                $valid = City::where('id', $cityId)
                    ->where('country_id', $countryId)
                    ->exists();

                if (!$valid) {
                    return $this->validationError([
                        'city' => ['Selected city does not belong to selected country.']
                    ], 422);
                }
            }

            // =========================
            // IMAGE UPLOAD (ONLY ONCE)
            // =========================
            $uploadedPath = null;

            if ($request->hasFile('profile_image') && $request->file('profile_image')->isValid()) {

                $file = $request->file('profile_image');

                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                $uploadPath = 'uploads/profile_images/';

                $file->move(public_path($uploadPath), $filename);

                $uploadedPath = $uploadPath . $filename;

                // delete old user image
                if (!empty($user->profile_image)) {
                    $oldPath = public_path($user->profile_image);
                    if (file_exists($oldPath) && is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }

                $user->profile_image = $uploadedPath;
            }

            // =========================
            // USER UPDATE
            // =========================
            $user->name = $request->name ?? $user->name;
            $user->country_id = $countryId;
            $user->city_id = $cityId;

            // =========================
            // PASSWORD UPDATE
            // =========================
            if ($request->filled('current_password')) {

                if (!Hash::check($request->current_password, $user->password)) {
                    return $this->errors([], 'Current password is incorrect.', 422);
                }

                $user->password = Hash::make($request->new_password);
            }

            $user->save();
            $user->loadMissing(['country:id,name', 'city:id,name,country_id']);

            // =========================
            // ATHLETE/COACH/CLUB PROFILE UPDATE
            // =========================
            $privacy_settings_obj = null;

            if ($user->role == 'player') {

                $privacy_settings_obj = AthleteProfiles::where('user_id', $user->id)->first();

                if ($privacy_settings_obj) {

                    $privacy_settings_obj->name = $request->name ?? $user->name;

                    // ✅ reuse uploaded image
                    if ($uploadedPath) {

                        if (!empty($privacy_settings_obj->image)) {
                            $oldPath = public_path($privacy_settings_obj->image);
                            if (file_exists($oldPath) && is_file($oldPath)) {
                                unlink($oldPath);
                            }
                        }

                        $privacy_settings_obj->image = $uploadedPath;
                    }

                    if ($request->has('privacy_settings')) {
                        $privacy_settings_obj->privacy_settings = $request->privacy_settings;
                    }

                    $privacy_settings_obj->save();
                }
            } elseif ($user->role == 'coach') {
                $privacy_settings_obj = Coach::where('user_id', $user->id)->first();
                if ($privacy_settings_obj) {
                    $privacy_settings_obj->name = $request->name ?? $user->name;
                    if ($uploadedPath) {
                        if (!empty($privacy_settings_obj->coach_profile_pic)) {
                            $oldPath = public_path($privacy_settings_obj->coach_profile_pic);
                            if (file_exists($oldPath) && is_file($oldPath)) {
                                unlink($oldPath);
                            }
                        }
                        $privacy_settings_obj->coach_profile_pic = $uploadedPath;
                    }
                    if ($request->has('privacy_settings')) {
                        $privacy_settings_obj->privacy_settings = $request->privacy_settings;
                    }

                    if ($request->has('visible_reviews')) {
                        $privacy_settings_obj->visible_reviews = $request->visible_reviews;
                    }


                    if ($request->has('allow_parent_player_reviews')) {
                        $privacy_settings_obj->allow_parent_player_reviews = $request->allow_parent_player_reviews;
                    }

                    $privacy_settings_obj->save();
                }
            } elseif ($user->role == 'club') {
                $privacy_settings_obj = ClubProfile::where('user_id', $user->id)->first();
                if ($privacy_settings_obj) {
                    $privacy_settings_obj->club_name = $request->name ?? $user->name;
                    if ($uploadedPath) {
                        if (!empty($privacy_settings_obj->club_logo)) {
                            $oldPath = public_path($privacy_settings_obj->club_logo);
                            if (file_exists($oldPath) && is_file($oldPath)) {
                                unlink($oldPath);
                            }
                        }
                        $privacy_settings_obj->club_logo = $uploadedPath;
                    }
                    if ($request->has('privacy_settings')) {
                        $privacy_settings_obj->privacy_settings = $request->privacy_settings;
                    }
                    $privacy_settings_obj->save();
                }
            }

            // =========================
            // RESPONSE
            // =========================
            $data = [
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' => $user->profile_image ? asset($user->profile_image) : null,
                'country_id' => $user->country_id,
                'city_id' => $user->city_id,
                'country' => $user->country?->name,
                'city' => $user->city?->name,
                'privacy_settings' => $privacy_settings_obj->privacy_settings ?? null,
            ];

            return $this->success($data, 'Parent profile updated successfully', 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 422);
        }

        try {
            $user = Auth::guard('api')->user();

            if (! Hash::check($request->current_password, $user->password)) {
                return $this->validationError(['current_password' => ['Current password is incorrect.']], 422);
            }

            $user->password = bcrypt($request->new_password);
            $user->save();

            return $this->success(null, 'Password changed successfully', 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }
}
