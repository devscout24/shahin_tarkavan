<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\ParentAggrements;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
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
        $user = new User();
        $user->name = $request->name;
        $user->last_name = $request->last_name ?? null;
        $user->email = $request->email;
        $user->password = bcrypt($request->password); // ⚠️ important (hash)
        $user->role = $request->role;
        if ($request->role == 'coach') {
           $user->status = 'pending';
        }
        else{
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

    public function aggrement(Request $request){

      $validator = Validator::make($request->all(), [

            'is_agree' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 422);
        }

    try {
            $user =Auth::guard('api')->user();
            $aggrement = new ParentAggrements();
            $aggrement->user_id = $user->id;
            $aggrement->agreed = $request->is_agree;
            $aggrement->save();

            $data=  [
                'user_id' => $user->id,
                'is_agree' => $request->is_agree,
                'user_name' => $user->name,
            ];

            return $this->success($data, 'Aggrement updated successfully', 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function getProfile(Request $request){
        try {
            $user =Auth::guard('api')->user();
            if($user->role=='player'){
                $privacy_settings =AthleteProfiles::where('user_id',$user->id)->value('privacy_settings');
            }
             $data=[
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' =>asset($user->profile_image),
                'privacy_settings' => $privacy_settings ?? null,
             ];
            return $this->success($data, 'Parent profile retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    public function UpdateParentProfile(Request $request){

    try{

            $user =Auth::guard('api')->user();
            $user->name = $request->name ?? $user->name;
            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $filePath = 'uploads/profile_images/' . $filename;
                $file->move(public_path('uploads/profile_images'), $filename);
                $user->profile_image = $filePath;
            }
            $user->save();


            if($user->role=='player'){
                $privacy_settings =AthleteProfiles::where('user_id',$user->id)->first();
                if($privacy_settings){
                    $privacy_settings->privacy_settings = $request->privacy_settings ?? $privacy_settings->privacy_settings;
                    $privacy_settings->save();
                }
            }


             $data=[
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' =>asset($user->profile_image),
                'privacy_settings' => $privacy_settings->privacy_settings ?? null,
             ];

            return $this->success($data, 'Parent profile updated successfully', 200);
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }
}
