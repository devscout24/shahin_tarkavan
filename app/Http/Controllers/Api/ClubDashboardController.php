<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubMatch;
use App\Models\ClubSubscription;
use App\Models\RecruitementApply;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ClubDashboardController extends Controller
{
    use ApiResponse;

    private function formatMatchCard(ClubMatch $match, int $clubId, bool $isRequested): array
    {
        $match->loadMissing([
            'clubTeam:id,club_id,name,age_group,image,competition_level_id',
            'clubTeam.competitionLevel:id,name',
            'clubTeam.club:id,name,last_name,email',
            'clubTeam.club.club:id,user_id,club_name,club_logo,city,state,country',
            'opponentClub:id,user_id,club_name,club_logo,city,state,country',
        ]);

        $team = $match->clubTeam;
        $competitionLevel = $team?->competitionLevel?->name;
        $ageGroup = $team?->age_group;
        $formattedAge = array_values(array_filter([
            $competitionLevel,
            $ageGroup ? 'Age: ' . $ageGroup : null,
        ]));

        return [
            'id' => $match->id,
            'headline' => 'Friendly Match Available',
            'club' => [
                'id' => $team?->club?->club?->id,
                'club_name' => $team?->club?->club?->club_name,
                'club_logo' => ! empty($team?->club?->club?->club_logo) ? asset($team->club->club->club_logo) : null,
                'city' => $team?->club?->club?->city,
                'state' => $team?->club?->club?->state,
                'country' => $team?->club?->club?->country,
            ],
            'team' => [
                'id' => $team?->id,
                'name' => $team?->name,
                'age_group' => $team?->age_group,
                'image' => ! empty($team?->image) ? asset($team->image) : null,
                'competition_level' => $competitionLevel,
                'formatted_age' => implode(' | ', $formattedAge) ?: null,
            ],
            'available_date' => $match->available_date
                ? ($match->available_date instanceof CarbonInterface
                    ? $match->available_date->format('F d, Y')
                    : Carbon::parse($match->available_date)->format('F d, Y'))
                : null,
            'location' => $match->location,
            'field_opportunity' => $match->field_opportunity,
            'opponent_club' => [
                'id' => $match->opponentClub?->id,
                'club_name' => $match->opponentClub?->club_name,
                'club_logo' => ! empty($match->opponentClub?->club_logo) ? asset($match->opponentClub->club_logo) : null,
                'city' => $match->opponentClub?->city,
                'state' => $match->opponentClub?->state,
                'country' => $match->opponentClub?->country,
            ],
            'status' => $match->status,
            'is_requested' => $isRequested,
            'action_label' => $isRequested ? 'Requested Match' : 'Request Match',
        ];
    }

    public function clubDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return $this->errors([], 'Authentication required.', 401);
            }

            if ($user->role !== 'club') {
                return $this->errors([], 'Only club can view this dashboard.', 403);
            }

            $subscription = ClubSubscription::query()
                ->with(['subscriptionPlan:id,name,price'])
                ->where('club_id', $user->id)
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if (! $subscription) {
                return $this->errors([], 'Active club subscription not found. Please subscribe to access the dashboard.', 403);
            }

            $club = $user->club;
            $today = now()->toDateString();

            $teamIds = $user->clubTeams()->pluck('id')->all();

            $recentOpportunitiesQuery = ClubMatch::query()
                ->with([
                    'clubTeam:id,club_id,name,age_group,image,competition_level_id',
                    'clubTeam.competitionLevel:id,name',
                    'clubTeam.club:id,name,last_name,email',
                    'clubTeam.club.club:id,user_id,club_name,club_logo,city,state,country',
                    'opponentClub:id,user_id,club_name,club_logo,city,state,country',
                ])
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereDate('available_date', '>=', $today)
                ->whereHas('clubTeam', function ($query) use ($user): void {
                    $query->where('club_id', '!=', $user->id);
                })
                ->orderBy('available_date')
                ->limit(5);

            if (! empty($teamIds)) {
                $recentOpportunitiesQuery->where(function ($query) use ($teamIds): void {
                    $query->whereNotIn('club_team_id', $teamIds)
                        ->orWhereNull('club_team_id');
                });
            }

            $recentOpportunities = $recentOpportunitiesQuery
                ->get()
                ->map(function (ClubMatch $match) use ($user): array {
                    $isRequested = $match->matchBids()
                        ->where('requested_club_id', $user->club?->id)
                        ->exists();

                    return $this->formatMatchCard($match, (int) $user->id, $isRequested);
                })
                ->values();

            $activeTeams = $user->clubTeams()->count();
            $playerApplications = RecruitementApply::query()
                ->where('club_id', $user->id)
                ->where('type', 'player')
                ->count();

            $coachApplications = RecruitementApply::query()
                ->where('club_id', $user->id)
                ->where('type', 'coach')
                ->count();

            $upcomingMatches = ClubMatch::query()
                ->whereIn('club_team_id', $teamIds)
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereDate('available_date', '>=', $today)
                ->count();

            return $this->success([
                'club_info' => [
                    'id' => $club?->id,
                    'club_name' => $club?->club_name,
                    'club_logo' => ! empty($club?->club_logo) ? asset($club->club_logo) : null,
                    'city' => $club?->city,
                    'state' => $club?->state,
                    'country' => $club?->country,
                ],
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'plan' => [
                        'id' => $subscription->subscriptionPlan?->id,
                        'name' => $subscription->subscriptionPlan?->name,
                        'price' => $subscription->subscriptionPlan?->price,
                    ],
                    'current_period_end' => optional($subscription->current_period_end)?->toDateString(),
                ],
                'summary' => [
                    'active_teams' => $activeTeams,
                    'player_applications' => $playerApplications,
                    'coach_applications' => $coachApplications,
                    'upcoming_matches' => $upcomingMatches,
                ],
                'recent_opportunities' => $recentOpportunities,

            ], 'Club dashboard data fetched successfully.', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

  public function updateSettings(Request $request){


         $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

     try{

          $user = Auth::guard('api')->user();

          $currentPassword  = $request->input('current_password');
          $newPassword = $request->input('new_password');

        $usercheck = Hash::check($currentPassword, $user->password);
       if(!$usercheck){
            return $this->errors([], 'Current password is incorrect.', 422);
        }

        $user->password = Hash::make($newPassword);
        $user->name = $request->input('name', $user->name);
        if($request->hasFile('club_logo')){
             if (! empty($user->club_logo) && file_exists(public_path($user->club_logo))) {
                unlink(public_path($user->club_logo));
            }
            $logo = $request->file('club_logo');
            $logoName = time() . '_logo.' . $logo->getClientOriginalExtension();
            $logo->move(public_path('uploads/club_logos'), $logoName);
            $user->club_logo = 'uploads/club_logos/' . $logoName;
        }
        $user->save();

        $clubProfile = $user->club;
        if ($clubProfile) {
            $clubProfile->privacy_settings =$request->privacy_settings?? $clubProfile->privacy_settings;
            $clubProfile->save();
        }


        return $this->success( $clubProfile, 'Club settings updated successfully.', 200);

     }
     catch(\Throwable $e){
        return $this->errors([], $e->getMessage(), 500);
     }
  }

}
