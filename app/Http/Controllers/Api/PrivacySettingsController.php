<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
class PrivacySettingsController extends Controller
{
   public function privacySettings(Request $request)
{
    $user = Auth::guard('api')->user();

    try {
        $settings = null;

        if ($user->role == "player") {
            $settings = $user->playerProfile;
        } elseif ($user->role == "coach") {
            $settings = $user->coachProfile;
        } elseif ($user->role == "club") {
            $settings = $user->clubProfile;
        }


        if (!$settings) {
            return response()->json([
                'status' => false,
                ' data' => [],
                'message' => 'Profile not found.'
            ], 200);
        }

        $settings->privacy_settings = $request->input('privacy_settings', $settings->privacy_settings);
        $settings->save();

        return response()->json([
            'status' => true,
            'message' => 'Privacy settings updated successfully.',
            'data' => $settings
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'An error occurred while updating privacy settings.',
            'error' => $e->getMessage()
        ], 500);
    }
}

 public function getPrivacySettings(){
    try{
        $user = Auth::guard('api')->user();
        $settings = null;

        if ($user->role == "player") {
            $settings = $user->playerProfile;
        } elseif ($user->role == "coach") {
            $settings = $user->coachProfile;
        } elseif ($user->role == "club") {
            $settings = $user->clubProfile;
        }

        if (!$settings) {
            return response()->json([
                'status' => false,
                'data' => [],
                'message' => 'Profile not found.'
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'Privacy settings fetched successfully.',
            'data' => $settings
        ], 200);
    }
    catch(\Exception $e){
        return response()->json([
            'status' => false,
            'message' => 'An error occurred while fetching privacy settings.',
            'error' => $e->getMessage()
        ], 500);
 }


    }

  public function getCompetitionClubs(){
    try{
         $CompetitionLevels = \App\Models\CompetitionLevel::with('teams')->get();

         return response()->json([
            'status' => true,
            'message' => 'Competition clubs fetched successfully.',
            'data' => $CompetitionLevels
         ]);
    }
    catch(\Exception $e){
        return response()->json([
            'status' => false,
            'message' => 'An error occurred while fetching competition clubs.',
            'error' => $e->getMessage()
        ], 500);
    }
  }


}