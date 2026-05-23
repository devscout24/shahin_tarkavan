<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\ClubRecruitment;
use App\Models\ClubSubscription;
use App\Models\RecruitementApply;
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
            'player_position' => 'nullable|exists:player_positions,id',
            'coach_position_id' => 'nullable|exists:coach_positions,id',
            'team_id' => 'required|exists:club_teams,id',
            'upto_age' => 'nullable',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'recruitment_type' => 'required|in:player,coach',
            'gender' => 'nullable|in:male,female,other',
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
            $recruitement->start_date = $request->start_date;
            $recruitement->end_date = $request->end_date;
            $recruitement->description = $request->description;
            $recruitement->upto_age = $request->upto_age;
            $recruitement->gender = $request->gender;
            $recruitement->recruitment_type = $request->recruitment_type;
            $recruitement->save();

            return $this->success($recruitement, 'Recruitment created successfully', 201);
        } catch (\Throwable $e) {
            return $this->errors([], 'An error occurred while creating recruitment: ' . $e->getMessage(), 500);
        }
    }



    public function update(Request $request, $recruitment_id)
    {
        try {

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
            $validator = Validator::make($request->all(), [
                'player_position' => 'nullable|exists:player_positions,id',
                'coach_position_id' => 'nullable|exists:coach_positions,id',
                'team_id' => 'required|exists:club_teams,id',
                'upto_age' => 'nullable',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'recruitment_type' => 'required|in:player,coach',
                'gender' => 'nullable|in:male,female,other',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors(), 'Validation failed', 422);
            }

            $recruitement->player_position = $request->player_position;
            $recruitement->coach_position_id = $request->coach_position_id;
            $recruitement->club_team_id = $request->team_id;
            $recruitement->experience = $request->experience;
            $recruitement->start_date = $request->start_date;
            $recruitement->end_date = $request->end_date;
            $recruitement->description = $request->description;
            $recruitement->upto_age = $request->upto_age;
            $recruitement->gender = $request->gender;
            $recruitement->recruitment_type = $request->recruitment_type;
            $recruitement->save();
            return $this->success($recruitement, 'Recruitment updated successfully', 200);
        } catch (\Throwable $e) {
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
                ->with([
                    'playerPosition:id,name',
                    'coachPosition:id,name',
                    'clubTeam:id,name',
                    'recruitementApplies.user:id,name,last_name,profile_image,role',
                    'recruitementApplies.user.coachProfile:id,user_id,name,last_name,coach_profile_pic,sport_option_id,sports,current_role',
                    'recruitementApplies.user.athleteProfile:id,user_id,name,last_name,image,primary_position',
                    'recruitementApplies.child:id,user_id,name,last_name,image,dob,primary_position',
                    'recruitementApplies.child.primaryPosition:id,name',
                    'recruitementApplies.recruitment:id,club_id,club_team_id,player_position,coach_position_id,recruitment_type,end_date',
                    'recruitementApplies.recruitment.clubTeam:id,name',
                    'recruitementApplies.recruitment.playerPosition:id,name',
                    'recruitementApplies.recruitment.coachPosition:id,name',
                ])
                ->where('id', $recruitment_id)

                ->where('club_id', $user->id)
                ->first();


            if (!$recruitement) {
                return $this->notFound([], 'Recruitment not found', 404);
            }

            return $this->success([
                'recruitment' => $recruitement,
                'applicants' => $recruitement->recruitementApplies->map(function (RecruitementApply $application) {
                    $isParentChild = $application->type === 'parent' && $application->child;
                    $applicantProfile = $this->resolveApplicantProfile($application);
                    $applicantName = $this->resolveApplicantName($applicantProfile);
                    $applicantImage = $this->resolveApplicantImage($application);

                    $profileId = null;
                    if ($isParentChild) {
                        $profileId = $application->child_id;
                    } elseif ($application->type === 'coach') {
                        $profileId = $application->user?->coachProfile?->id;
                    } elseif ($application->type === 'player') {
                        $profileId = $application->user?->athleteProfile?->id;
                    }

                    return [
                        'application_id' => $application->id,
                        'type' => $application->type,
                        'status' => $application->status,
                        'name' => $applicantName,
                        'role' => $isParentChild ? 'player' : $application->type,
                        'user_id' => $application->user_id,
                        'profile_id' => $profileId,
                        'profile' => [
                            'name' => $applicantName,
                            'image' => $applicantImage,
                            'type' => $isParentChild ? 'child' : $application->type,
                        ],
                        'recruitment' => [
                            'id' => $application->recruitment?->id,
                            'type' => $application->recruitment?->recruitment_type,
                            'team_name' => $application->recruitment?->clubTeam?->name,
                            'player_position' => $application->recruitment?->playerPosition?->name,
                            'coach_position' => $application->recruitment?->coachPosition?->name,
                            'start_date' => $application->recruitment?->start_date
                                ? (
                                    $application->recruitment->start_date instanceof \Carbon\CarbonInterface
                                    ? $application->recruitment->start_date->toDateString()
                                    : \Carbon\Carbon::parse($application->recruitment->start_date)->toDateString()
                                )
                                : null,
                            'end_date' => $application->recruitment?->end_date
                                ? (
                                    $application->recruitment->end_date instanceof \Carbon\CarbonInterface
                                    ? $application->recruitment->end_date->toDateString()
                                    : \Carbon\Carbon::parse($application->recruitment->end_date)->toDateString()
                                )
                                : null,
                        ],
                        'profile_image' => $applicantImage,
                    ];
                })->values(),
            ], 'Recruitment details fetched successfully', 200);
        } catch (\Throwable $e) {
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

            ClubRecruitment::query()
                ->whereKey($recruitement->id)
                ->where('club_id', $user->id)
                ->delete();

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

    private function resolveApplicantName($model): ?string
    {
        if (! $model) {
            return null;
        }

        $firstName = trim((string) ($model->name ?? ''));
        $lastName = trim((string) ($model->last_name ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);

        return $fullName !== '' ? $fullName : null;
    }

    private function resolveApplicantProfile(RecruitementApply $application)
    {
        if ($application->type === 'parent' && $application->child) {
            return $application->child;
        }

        if ($application->type === 'coach') {
            return $application->user?->coachProfile ?: $application->user;
        }

        if ($application->type === 'player') {
            return $application->user?->athleteProfile ?: $application->user;
        }

        return $application->child ?: $application->user;
    }

    private function resolveApplicantImage(RecruitementApply $application): ?string
    {
        if ($application->type === 'parent' && $application->child?->image) {
            return asset($application->child->image);
        }

        if ($application->type === 'coach' && $application->user?->coachProfile?->coach_profile_pic) {
            return asset($application->user->coachProfile->coach_profile_pic);
        }

        if ($application->type === 'player' && $application->user?->athleteProfile?->image) {
            return asset($application->user->athleteProfile->image);
        }

        return ! empty($application->user?->profile_image) ? asset($application->user->profile_image) : null;
    }
}
