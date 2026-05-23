<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubSubscription;
use App\Models\ClubTeam;
use App\Models\RecruitementApply;
use App\Models\TeamPlayer;
use App\Support\AgeGroup;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClubTeamController extends Controller
{
    use   ApiResponse;

    private function resolveTeamName(Request $request): ?string
    {
        foreach (['name', 'team_name', 'club_team_name'] as $key) {
            $value = trim((string) $request->input($key));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'name' => 'required|string|max:255',
            // 'age_group' => ['nullable', Rule::in(AgeGroup::labels())],
            'age_group' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'competition_level_id' => 'nullable|exists:competition_levels,id',
        ]);

        if ($validator->fails()) {
            return $this->errors($validator->errors(), 'Validation failed', 422);
        }
        try {
            $club_id = Auth::guard('api')->user()->id;

            $clubSubscription = ClubSubscription::query()
                ->where('club_id', $club_id)
                ->where('status', 'active')
                ->first();

            if (! $clubSubscription) {
                return $this->notFound([], 'Active club subscription not found.', 404);
            }



            $team = new ClubTeam();
            $team->club_id = $club_id;
            $team->name = $this->resolveTeamName($request);
            $team->age_group = AgeGroup::normalize($request->age_group) ?? $request->age_group;
            $team->gender = $request->gender;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = "uploads/club_teams/";
                $file->move(public_path($path), $filename);
                $team->image = $path . $filename;
            }
            $team->competition_level_id = $request->competition_level_id;
            $team->save();
            return $this->success($team, 'Club team created successfully', 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching club subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function list(Request $request)
    {
        try {
            $club_id = Auth::guard('api')->user()->id;

            $clubSubscription = ClubSubscription::query()
                ->where('club_id', $club_id)
                ->where('status', 'active')
                ->first();

            if (!$clubSubscription) {
                return $this->notFound([], 'Active club subscription not found.', 200);
            }

            $perPage = $request->input('per_page', 10);

            $teams = ClubTeam::query()
                ->with('competitionLevel:id,name')
                ->where('club_id', $club_id)
                ->orderBy('created_at', 'desc')
                ->withCount([
                    'teamPlayers as total_players' => function ($q) {
                        $q->where(function ($query) {
                            $query->whereNotNull('player_id')
                                ->orWhereNotNull('child_id');
                        });
                    },
                    'teamPlayers as total_coaches' => function ($q) {
                        $q->whereNotNull('coach_id');
                    }
                ])
                ->paginate($perPage);

            if ($teams->isEmpty()) {
                return $this->notFound([], 'Club teams not found.', 200);
            }

            // image asset path add
            $data = collect($teams->items())->map(function ($team) {

                $team->image = $team->image
                    ? asset($team->image)
                    : null;

                return $team;
            });

            return response()->json([
                'status' => true,
                'message' => 'Club teams fetched successfully',
                'data' => $data,
                'meta' => [
                    'current_page' => $teams->currentPage(),
                    'last_page' => $teams->lastPage(),
                    'per_page' => $teams->perPage(),
                    'total' => $teams->total(),
                ]
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching club teams: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $club_id = Auth::guard('api')->user()->id;

            $clubSubscription = ClubSubscription::query()
                ->where('club_id', $club_id)
                ->where('status', 'active')
                ->first();

            if (! $clubSubscription) {
                return $this->notFound([], 'Active club subscription not found.', 404);
            }

            $team = ClubTeam::query()
                ->where('club_id', $club_id)
                ->where('id', $id)
                ->first();

            if (! $team) {
                return $this->notFound([], 'Club team not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'age_group' => ['nullable', Rule::in(AgeGroup::labels())],
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
                'gender' => 'nullable|in:male,female,other',
                'competition_level_id' => 'nullable|exists:competition_levels,id',
            ]);

            if ($validator->fails()) {
                return $this->errors($validator->errors(), 'Validation failed', 422);
            }

            $team->name = $this->resolveTeamName($request);
            $team->age_group = AgeGroup::normalize($request->age_group) ?? $request->age_group;
            $team->gender = $request->gender;
            if ($request->hasFile('image')) {

                if ($team->image) {
                    unlink(public_path($team->image));
                }

                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = "uploads/club_teams/";
                $file->move(public_path($path), $filename);
                $team->image = $path . $filename;
            }
            $team->competition_level_id = $request->competition_level_id;
            $team->save();

            return $this->success($team, 'Club team updated successfully', 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error updating club team: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function delete($id)
    {
        try {
            $club_id = Auth::guard('api')->user()->id;

            $clubSubscription = ClubSubscription::query()
                ->where('club_id', $club_id)
                ->where('status', 'active')
                ->first();

            if (! $clubSubscription) {
                return $this->notFound([], 'Active club subscription not found.', 404);
            }

            $team = ClubTeam::query()
                ->where('club_id', $club_id)
                ->where('id', $id)
                ->first();

            if (! $team) {
                return $this->notFound([], 'Club team not found.', 404);
            }

            if ($team->image) {
                unlink(public_path($team->image));
            }

            $team->delete();

            return $this->success([], 'Club team deleted successfully', 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error deleting club team: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function show($id)
    {
        try {
            $club_id = Auth::guard('api')->user()->id;

            $clubSubscription = ClubSubscription::query()
                ->where('club_id', $club_id)
                ->where('status', 'active')
                ->first();

            if (! $clubSubscription) {
                return $this->notFound([], 'Active club subscription not found.', 404);
            }

            $team = ClubTeam::query()
                ->where('club_id', $club_id)
                ->where('id', $id)
                ->first();

            if (! $team) {
                return $this->notFound([], 'Club team not found.', 404);
            }

            return $this->success($team, 'Club team details fetched successfully', 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching club team details: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function listTeamPlayersandCoaches($team_id)
    {
        try {
            $club_id = Auth::guard('api')->user()->id;

            $clubSubscription = ClubSubscription::query()
                ->where('club_id', $club_id)
                ->where('status', 'active')
                ->first();

            if (! $clubSubscription) {
                return $this->notFound([], 'Active club subscription not found.', 404);
            }

            $team = ClubTeam::query()
                ->where('club_id', $club_id)
                ->where('id', $team_id)
                ->first();

            if (! $team) {
                return $this->notFound([], 'Club team not found.', 404);
            }

            $coaches = TeamPlayer::query()
                ->where('team_id', $team->id)
                ->where('club_id', $club_id)
                ->whereNotNull('coach_id')
                ->whereHas('coach', function ($q) {
                    $q->where('role', 'coach');
                })
                ->with([
                    'coach:id,name,last_name,phone,address,profile_image,role',
                    'coach.coachProfile:id,user_id,dob,years_of_experience,current_role,coach_profile_pic,city,country',
                    'coach.coachProfile.currentPosition:id,name',
                ])
                ->get()
                ->unique('coach_id')
                ->values()
                ->map(function (TeamPlayer $item) {
                    $user = $item->coach;
                    $profile = $user?->coachProfile;

                    return [
                        'team_player_id' => $item->id,
                        'user_id' => $user?->id,
                        'profile_id' => $profile?->id,
                        'name' => $this->fullName($user?->name, $user?->last_name),
                        'role' => 'coach',
                        'age' => $this->resolveAge($profile?->dob),
                        'position' => $profile?->currentPosition?->name,
                        'experience' => $profile?->years_of_experience,
                        'phone' => $user?->phone,
                        'address' => $user?->address,
                        'city' => $profile?->city,
                        'country' => $profile?->country,
                        'profile_image' => $profile?->coach_profile_pic ? asset($profile->coach_profile_pic) : ($user?->profile_image ? asset($user->profile_image) : null),
                    ];
                })
                ->values();

            $players = TeamPlayer::query()
                ->where('team_id', $team->id)
                ->where('club_id', $club_id)
                ->where(function ($q) {
                    $q->whereNotNull('child_id')
                        ->orWhere(function ($sub) {
                            $sub->whereNotNull('player_id')
                                ->whereHas('player', function ($playerQ) {
                                    $playerQ->where('role', 'player');
                                });
                        });
                })
                ->with([
                    'player:id,name,last_name,profile_image,role',
                    'player.athleteProfile:id,user_id,dob,jersey_number,total_played_games,total_played_time,goals,assist,primary_position,image,city,country',
                    'player.athleteProfile.primaryPosition:id,name',
                    'child:id,parent_id,name,last_name,dob,jersey_number,total_played_games,total_played_time,goals,assist,primary_position,image,city,country',
                    'child.primaryPosition:id,name',
                ])
                ->get()
                ->map(function (TeamPlayer $item) {
                    $isChild = ! is_null($item->child_id) && $item->child;
                    $profile = $isChild ? $item->child : $item->player?->athleteProfile;

                    return [
                        'team_player_id' => $item->id,
                        'user_id' => $item->player_id,
                        'child_id' => $item->child_id,
                        'profile_id' => $profile?->id,
                        'name' => $isChild
                            ? $this->fullName($item->child?->name, $item->child?->last_name)
                            : $this->fullName($item->player?->name, $item->player?->last_name),
                        'role' => 'player',
                        'is_parent_child' => $isChild,
                        'age' => $this->resolveAge($profile?->dob),
                        'position' => $profile?->primaryPosition?->name,
                        'jersey_number' => $profile?->jersey_number,
                        'games' => (int) ($profile?->total_played_games ?? 0),
                        'total_played_time' => (int) ($profile?->total_played_time ?? 0),
                        'goals' => (int) ($profile?->goals ?? 0),
                        'assists' => (int) ($profile?->assist ?? 0),
                        'profile_image' => $profile?->image ? asset($profile->image) : ($isChild ? null : ($item->player?->profile_image ? asset($item->player->profile_image) : null)),
                        'city' => $profile?->city,
                        'country' => $profile?->country,
                    ];
                })
                ->values();

            return $this->success([
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'age_group' => $team->age_group,
                    'image' => $team->image,
                ],
                'coaches' => $coaches,
                'players' => $players,
                'summary' => [
                    'total_coaches' => $coaches->count(),
                    'total_players' => $players->count(),
                ],
            ], 'Team players and coaches fetched successfully', 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching team players/coaches: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function resolveAge($dob): ?int
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

    private function fullName(?string $firstName, ?string $lastName): ?string
    {
        $name = trim((string) $firstName . ' ' . (string) $lastName);

        return $name !== '' ? $name : null;
    }
    ///////////////////team player coach listing end//////////////////
 public function releasePlayer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'team_player_id' => 'required|exists:team_players,id',
        ]);

        if ($validator->fails()) {
            return $this->errors($validator->errors(), 'Validation failed', 422);
        }

        try {
            $teamPlayer = TeamPlayer::query()
                ->where('id', $request->input('team_player_id'))
                ->first();

            if (! $teamPlayer) {
                return $this->notFound([], 'Team player not found.', 404);
            }

            // Remove relevant recruitment applications
            RecruitementApply::query()
                ->where('team_id', $teamPlayer->team_id)
                ->where('club_id', $teamPlayer->club_id)
                ->where(function ($query) use ($teamPlayer) {
                    if ($teamPlayer->coach_id) {
                        $query->where('user_id', $teamPlayer->coach_id);
                    } elseif ($teamPlayer->player_id) {
                        $query->where('user_id', $teamPlayer->player_id);
                    } elseif ($teamPlayer->child_id) {
                        $query->where('child_id', $teamPlayer->child_id);
                    }
                })
                ->delete();

            $teamPlayer->delete();

            return $this->success([], 'Player/Coach released from team and recruitment successfully', 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error releasing player/coach: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function transferPlayer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'team_player_id' => 'required|exists:team_players,id',
            'new_team_id' => 'required|exists:club_teams,id',
        ]);

        if ($validator->fails()) {
            return $this->errors($validator->errors(), 'Validation failed', 422);
        }

        try {
            $club_id = Auth::guard('api')->user()->id;

            // Find the team player record
            $teamPlayer = TeamPlayer::query()
                ->where('id', $request->team_player_id)
                ->where('club_id', $club_id)
                ->first();

            if (!$teamPlayer) {
                return $this->notFound([], 'Team player record not found or unauthorized.', 404);
            }

            // Ensure the new team belongs to the same club
            $newTeam = ClubTeam::query()
                ->where('id', $request->new_team_id)
                ->where('club_id', $club_id)
                ->first();

            if (!$newTeam) {
                return $this->notFound([], 'Target team not found or unauthorized.', 404);
            }

            // Update recruitment applications to the new team_id if they exist
            RecruitementApply::query()
                ->where('team_id', $teamPlayer->team_id)
                ->where('club_id', $club_id)
                ->where(function ($query) use ($teamPlayer) {
                    if ($teamPlayer->coach_id) {
                        $query->where('user_id', $teamPlayer->coach_id);
                    } elseif ($teamPlayer->player_id) {
                        $query->where('user_id', $teamPlayer->player_id);
                    } elseif ($teamPlayer->child_id) {
                        $query->where('child_id', $teamPlayer->child_id);
                    }
                })
                ->update(['team_id' => $newTeam->id]);

            // Update the team_id
            $teamPlayer->team_id = $newTeam->id;
            $teamPlayer->save();

            return $this->success($teamPlayer, 'Player/Coach transferred to new team successfully', 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error transferring player/coach: ' . $e->getMessage(),
            ], 500);
        }
    }

}