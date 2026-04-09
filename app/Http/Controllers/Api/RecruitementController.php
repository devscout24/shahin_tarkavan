<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\ClubRecruitment;
use App\Models\ClubSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Throwable;
use App\Traits\ApiResponse;
class RecruitementController extends Controller
{
    use ApiResponse;

   public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'player_position' => 'required|exists:player_positions,id',
        'coach_position_id' => 'nullable|exists:coach_positions,id',
        'team_id' => 'required|exists:club_teams,id',
        'upto_age' => 'required|integer',
        'end_date' => 'required|date',
        'recruitment_type' => 'required|in:player,coach',
    ]);

    if ($validator->fails()) {
       return $this->validationError($validator->errors(), 'Validation failed', 422);
    }

    try {
        $user = Auth::guard('api')->user();

        $club_subscription = ClubSubscription::query()
            ->where('club_id', $user->id)
            ->where('status', 'active')
            ->first();
         if (!$club_subscription) {
            return $this->errors([], 'Your subscription has expired. Please renew to create a recruitment.', 403);
        }

        // dd($request->all()); // remove this

        $recruitement = new ClubRecruitment();
        $recruitement->club_id = $user->id;
        $recruitement->player_position = $request->player_position;
        $recruitement->coach_position_id = $request->coach_position_id;
        $recruitement->club_team_id = $request->team_id;
        $recruitement->experience = $request->experience;
        $recruitement->end_date = $request->end_date;
        $recruitement->description = $request->description;
        $recruitement->upto_age = $request->upto_age;
        $recruitement->recruitment_type = $request->recruitment_type;
        $recruitement->save();

        return $this->success($recruitement, 'Recruitment created successfully', 201);

    } catch (\Throwable $e) {
        return $this->errors([], 'An error occurred while creating recruitment: ' . $e->getMessage(), 500);
    }
}



public function update(Request $request, $recruitment_id)
{
   try{

    $user = Auth::guard('api')->user();
      $club_subscription = ClubSubscription::query()
            ->where('club_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$club_subscription) {
            return $this->errors([], 'Your subscription has expired. Please renew to update the recruitment.', 403);
        }



    $recruitement = ClubRecruitment::query()
        ->where('id', $recruitment_id)
        ->where('club_id', $user->id)
        ->first();
    if (!$recruitement) {
        return $this->notFound([], 'Recruitment not found', 404);
    }
    $recruitement->player_position = $request->player_position;
    $recruitement->coach_position_id = $request->coach_position_id;
    $recruitement->club_team_id = $request->team_id;
    $recruitement->experience = $request->experience;
    $recruitement->end_date = $request->end_date;
    $recruitement->description = $request->description;
    $recruitement->upto_age = $request->upto_age;
    $recruitement->recruitment_type = $request->recruitment_type;
    $recruitement->save();
    return $this->success($recruitement, 'Recruitment updated successfully', 200);
   }
   catch(\Throwable $e){
    return $this->errors([], 'An error occurred while updating recruitment: ' . $e->getMessage(), 500);
   }
}


public function show($recruitment_id)
{

      $user = Auth::guard('api')->user();
       $club_subscription = ClubSubscription::query()
            ->where('club_id', $user->id)
            ->where('status', 'active')
             ->first();

        if (!$club_subscription) {
            return $this->errors([], 'Your subscription has expired. Please renew to view the recruitment.', 403);
        }
    try {

        $recruitement = ClubRecruitment::query()
            ->with(['playerPosition:id,name', 'coachPosition:id,name', 'clubTeam:id,name'])
            ->where('id', $recruitment_id)

            ->where('club_id', $user->id)
            ->first();


        if (!$recruitement) {
            return $this->notFound([], 'Recruitment not found', 404);
        }

         return $this->success($recruitement, 'Recruitment details fetched successfully', 200);
        }
        catch (\Throwable $e) {
            return $this->errors([], 'An error occurred while fetching recruitment details: ' . $e->getMessage(), 500);
        }
    }

    public function delete($recruitment_id)
    {


        try {
            $user = Auth::guard('api')->user();
             $club_subscription = ClubSubscription::query()
            ->where('club_id', $user->id)
            ->where('status', 'active')
             ->first();

        if (!$club_subscription) {
            return $this->errors([], 'Your subscription has expired. Please renew to delete the recruitment.', 403);
        }

            $recruitement = ClubRecruitment::query()
                ->where('id', $recruitment_id)
                ->where('club_id', $user->id)
                ->first();

            if (!$recruitement) {
                return $this->notFound([], 'Recruitment not found', 404);
            }

            $recruitement->delete();

             return $this->success([], 'Recruitment deleted successfully', 200);
        } catch (\Throwable $e) {
            return $this->errors([], 'An error occurred while deleting recruitment: ' . $e->getMessage(), 500);
        }
    }

    public function list()
    {
        try {
            $user = Auth::guard('api')->user();
             $club_subscription = ClubSubscription::query()
            ->where('club_id', $user->id)
            ->where('status', 'active')
             ->first();

        if (!$club_subscription) {
            return $this->errors([], 'Your subscription has expired. Please renew to view recruitments.', 403);
        }

            $recruitments = ClubRecruitment::query()
                ->with(['playerPosition:id,name', 'coachPosition:id,name', 'clubTeam:id,name'])
                ->where('club_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

             return $this->success($recruitments, 'Recruitments fetched successfully', 200);
        } catch (\Throwable $e) {
            return $this->errors([], 'An error occurred while fetching recruitments: ' . $e->getMessage(), 500);
        }
    }
}
