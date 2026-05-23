<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RecruitementMail;
use App\Models\ClubRecruitment;
use App\Models\ClubSubscription;
use App\Models\ClubTeam;
use App\Models\RecruitementApply;
use App\Models\TeamPlayer;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;


class PlayerCoachRecruitmentController extends Controller
{
    use ApiResponse;
    public function apply(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'recruitment_id' => 'required',

        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 422);
        }

        try {


            $user = Auth::guard('api')->user();
            $checkAlreadyApplied = RecruitementApply::query()
                ->where('recruitment_id', $request->recruitment_id)
                ->where('team_id', $request->team_id)
                ->where('child_id', $request->child_id)
                ->where('club_id', $request->club_id)
                ->where('type', $request->type)
                ->where('user_id', $user->id)
                ->first();

            if ($checkAlreadyApplied) {
                return $this->validationError('You have already applied for this recruitment', 422);
            }


            $reqruitment = ClubRecruitment::query()->find($request->recruitment_id);


            $application = new RecruitementApply();
            $application->recruitment_id = $request->recruitment_id;
            $application->team_id = $reqruitment->club_team_id;
            $application->user_id = $user->id;
            if ($user->role == 'parent') {
                $application->child_id = $request->child_id;
            }
            $application->club_id = $reqruitment->club_id;
            $application->type = $user->role;
            $application->save();


            if ($application) {
                $TeamPlayer = new TeamPlayer();
                $TeamPlayer->team_id =  $reqruitment->club_team_id;
                if ($user->role == 'parent') {
                    $TeamPlayer->child_id = $request->child_id;
                    $TeamPlayer->parent_id = $user->id;
                } else {
                    $TeamPlayer->player_id = $user->id;
                }

                if ($reqruitment->recruitment_type == 'coach') {
                $TeamPlayer->coach_id = $user->id;
                }
                $TeamPlayer->club_id = $reqruitment->club_id;


                $TeamPlayer->save();
            }

            return $this->success($application, 'Application submitted successfully');
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }


    //list start
    public function listApplications(Request $request)
    {
        try {

             $subscription = ClubSubscription::query()
                ->where('club_id', Auth::guard('api')->user()->id)
                ->where('status', 'active')
                ->first();
                if (! $subscription) {
                    return $this->notFound([], 'Active club subscription not found.', 200);
                }







            $user = Auth::guard('api')->user();

            $query = RecruitementApply::query()
                   ->where('club_id', $user->id)
                ->with([
                    'team:id,name,age_group',
                    'recruitment:id,club_team_id,end_date,recruitment_type',
                    'recruitment.clubTeam:id,name,age_group',
                    'user:id,name,last_name,profile_image,email,phone',
                    'user.athleteProfile:id,user_id,name,last_name,dob,image,primary_position',
                    'user.athleteProfile.primaryPosition:id,name',
                    'user.coachProfile:id,user_id,name,last_name,dob,coach_profile_pic,current_role',
                    'user.coachProfile.currentPosition:id,name',
                    'child:id,parent_id,user_id,name,last_name,dob,image,primary_position',
                    'child.primaryPosition:id,name',
                ]);

            if ($user->role === 'club') {
                $query->where('club_id', $user->id);
            } else {
                $query->where('user_id', $user->id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('team_id')) {
                $query->where('team_id', $request->team_id);
            }

            $applications = $query
                ->latest('id')
                ->get();

            $data = $applications->map(function (RecruitementApply $application) {
                $isParentChild = $application->type === 'parent' && $application->child;

                $athlete = $application->user?->athleteProfile;
                $coach = $application->user?->coachProfile;
                $child = $application->child;

                $nameSource = $isParentChild ? $child : ($application->type === 'coach' ? $coach : ($athlete ?: $application->user));
                $position = null;
                $dob = null;
                $profileImage = null;

                if ($isParentChild && $child) {
                    $position = $child->primaryPosition?->name;
                    $dob = $child->dob;
                    $profileImage = $child->image;
                } elseif ($application->type === 'coach' && $coach) {
                    $position = $coach->currentPosition?->name;
                    $dob = $coach->dob;
                    $profileImage = $coach->coach_profile_pic;
                } elseif ($athlete) {
                    $position = $athlete->primaryPosition?->name;
                    $dob = $athlete->dob;
                    $profileImage = $athlete->image;
                }

                if (!$profileImage) {
                    $profileImage = $application->user?->profile_image;
                }

                $team = $application->team ?: $application->recruitment?->clubTeam;

                return [
                    'application_id' => $application->id,
                    'type' => $application->type,
                    'status' => $application->status,
                    'player_name' => $this->resolveDisplayName($nameSource),
                    'position' => $position,
                    'team_name' => $team?->name,
                    'age' => $this->resolveAgeFromDob($dob),
                    'tryout_date' => $application->recruitment?->end_date,
                    'profile_image' => $profileImage,
                    'applicant' => [
                        'user_id' => $application->user_id,
                        'child_id' => $application->child_id,
                        'club_id' => $application->club_id,
                    ],
                ];
            })->values();

            return $this->success($data, 'Applications retrieved successfully');
        } catch (\Exception $e) {
            return $this->errors($e->getMessage(), 500);
        }
    }

    private function resolveDisplayName($model): ?string
    {
        if (! $model) {
            return null;
        }

        $first = trim((string) ($model->name ?? ''));
        $last = trim((string) ($model->last_name ?? ''));
        $full = trim($first . ' ' . $last);

        return $full !== '' ? $full : null;
    }

    private function resolveAgeFromDob($dob): ?int
    {
        if (! $dob) {
            return null;
        }

        try {
            return Carbon::parse($dob)->age;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // list end


    public function updateApplicationStatus(Request $request, $application_id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,accepted,rejected,scheduled',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 422);
        }

        try {

                $subscription = ClubSubscription::query()
                ->where('club_id', Auth::guard('api')->user()->id)
                ->where('status', 'active')
                ->first();
                if (! $subscription) {
                    return $this->notFound([], 'Active club subscription not found.', 200);
                }




            $application = RecruitementApply::query()->where('club_id', Auth::guard('api')->user()->id)->find($application_id);

            if (! $application) {
                return $this->validationError([],'Application not found', 404);
            }

            $application->status = $request->status;
            if($request->status=='rejected'){
                // If rejected, remove the player from the team
                TeamPlayer::query()
                    ->where('team_id', $application->team_id)
                    ->where(function ($query) use ($application) {
                        if ($application->type === 'parent') {
                            $query->where('child_id', $application->child_id);
                        } else {
                            $query->where('player_id', $application->user_id);
                        }
                    })
                    ->delete();
            }
            $application->save();

            Mail::to($application->user->email)->send(new RecruitementMail($application));

            return $this->success([], 'Application status updated successfully');
        } catch (\Exception $e) {
            return $this->errors("something went wrong",$e->getMessage(), 500);
        }
    }


}
