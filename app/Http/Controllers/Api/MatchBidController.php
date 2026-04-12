<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendMatchBidStatusMailJob;
use App\Models\ClubMatch;
use App\Models\ClubProfile;
use App\Models\MatchBid;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MatchBidController extends Controller
{
    use ApiResponse;

    private function formatTeam($team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'age_group' => $team->age_group,
            'image' => ! empty($team->image) ? asset($team->image) : null,
            'competition_level' => $team->competitionLevel?->name,
        ];
    }

    private function formatClubProfile(?ClubProfile $clubProfile): ?array
    {
        if (! $clubProfile) {
            return null;
        }

        $clubProfile->loadMissing(['user.clubTeams.competitionLevel']);
        $team = $clubProfile->user?->clubTeams?->first();
        $formattedTeam = $team ? $this->formatTeam($team) : null;

        return [
            'id' => $clubProfile->id,
            'club_name' => $clubProfile->club_name,
            'email' => $clubProfile->email,
            'phone' => $clubProfile->phone,
            'city' => $clubProfile->city,
            'state' => $clubProfile->state,
            'country' => $clubProfile->country,
            'club_logo' => ! empty($clubProfile->club_logo) ? asset($clubProfile->club_logo) : null,
            'owner' => [
                'id' => $clubProfile->user?->id,
                'name' => trim((string) ($clubProfile->user?->name ?? '') . ' ' . (string) ($clubProfile->user?->last_name ?? '')),
                'email' => $clubProfile->user?->email,
            ],
            'team' => $formattedTeam,
        ];
    }

    private function resolveClubProfile(int|string|null $id): ?ClubProfile
    {
        if (empty($id)) {
            return null;
        }

        $clubProfile = ClubProfile::query()->with(['user.clubTeams.competitionLevel'])->find($id);

        if ($clubProfile) {
            return $clubProfile;
        }

        $user = User::query()->with(['club.user.clubTeams.competitionLevel'])->find($id);

        return $user?->club;
    }

    private function formatMatchBid(MatchBid $bid): array
    {
        $bid->loadMissing([
            'match.clubTeam.club.clubTeams.competitionLevel',
            'match.opponentClub.user.clubTeams.competitionLevel',
            'createdClub.user.clubTeams.competitionLevel',
            'requestedClub.user.clubTeams.competitionLevel',
        ]);

        return [
            'id' => $bid->id,
            'status' => $bid->status,
            'match' => [
                'id' => $bid->match?->id,
                'available_date' => $bid->match?->available_date,
                'location' => $bid->match?->location,
                'field_opportunity' => $bid->match?->field_opportunity,
                'team' => $bid->match?->clubTeam ? [
                    'id' => $bid->match->clubTeam->id,
                    'name' => $bid->match->clubTeam->name,
                    'age_group' => $bid->match->clubTeam->age_group,
                    'image' => ! empty($bid->match->clubTeam->image) ? asset($bid->match->clubTeam->image) : null,
                    'competition_level' => $bid->match->clubTeam->competitionLevel?->name,
                    'club' => [
                        'id' => $bid->match->clubTeam->club?->club?->id,
                        'club_name' => $bid->match->clubTeam->club?->club?->club_name,
                    ],
                ] : null,
                'opponent_club' => $this->formatClubProfile($bid->match?->opponentClub),
            ],
            'created_club' => $this->formatClubProfile($bid->createdClub) ?? $this->formatClubProfile($this->resolveClubProfile($bid->created_club_id)),
            'requested_club' => $this->formatClubProfile($bid->requestedClub) ?? $this->formatClubProfile($this->resolveClubProfile($bid->requested_club_id)),
        ];
    }

    public function placeBid(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'match_id' => 'required|exists:club_matches,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError(null, $validator->errors(), 422);
        }

        try {
            $user = Auth::guard('api')->user();

            if (! $user || $user->role !== 'club') {
                return $this->validationError(null, 'Only clubs can place bids', 422);
            }

            $match = ClubMatch::with(['clubTeam.club.club.user.clubTeams.competitionLevel'])->find($request->match_id);

            if (! $match) {
                return $this->error(null, 'Match not found.', 404);
            }

            $requestedClubProfile = $user->club;
            $createdClubProfile = $match->clubTeam?->club?->club;

            if (! $requestedClubProfile || ! $createdClubProfile) {
                return $this->validationError(null, 'Club profile information is missing.', 422);
            }

            if ((int) $requestedClubProfile->id === (int) $createdClubProfile->id) {
                return $this->validationError(null, 'You cannot place a bid on your own match.', 422);
            }

            $existingBid = MatchBid::query()
                ->where('match_id', $match->id)
                ->where('requested_club_id', $requestedClubProfile->id)
                ->first();

            if ($existingBid) {
                return $this->validationError(null, 'This club has already placed a bid for this match.', 422);
            }

            $bid = MatchBid::create([
                'match_id' => $match->id,
                'requested_club_id' => $requestedClubProfile->id,
                'created_club_id' => $createdClubProfile->id,
                'status' => 'pending',
            ]);

            return $this->success($this->formatMatchBid($bid), 'Bid placed successfully', 201);
        } catch (\Exception $e) {
            return $this->error(null, 'Failed to place bid: ' . $e->getMessage(), 500);
        }
    }

    public function listBidsForMatch(Request $request)
    {
        try {
            $matchId = $request->query('match_id');

            $matchbids = MatchBid::query()
                ->with([
                    'match.clubTeam.club.clubTeams.competitionLevel',
                    'match.opponentClub.user.clubTeams.competitionLevel',
                    'createdClub.user.clubTeams.competitionLevel',
                    'requestedClub.user.clubTeams.competitionLevel',
                ])
                ->when($matchId, fn($query) => $query->where('match_id', $matchId))
                ->latest('id')
                ->get()
                ->map(fn(MatchBid $bid) => $this->formatMatchBid($bid))
                ->values();

            return $this->success($matchbids, 'Bids retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->error(null, 'Failed to retrieve bids: ' . $e->getMessage(), 500);
        }
    }

    public function updateBidStatus(Request $request, $bid_id)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
        ]);

        if ($validator->fails()) {
            return $this->validationError(null, $validator->errors(), 422);
        }

        try {
            $user = Auth::guard('api')->user();

            if (! $user || $user->role !== 'club') {
                return $this->validationError(null, 'Only clubs can update bid status.', 422);
            }

            $bid = MatchBid::with([
                'match.clubTeam.club.club.user.clubTeams.competitionLevel',
                'match.opponentClub.user.clubTeams.competitionLevel',
                'createdClub.user.clubTeams.competitionLevel',
                'requestedClub.user.clubTeams.competitionLevel',
            ])->find($bid_id);



            if (! $bid) {
                return $this->error(null, 'Bid not found.', 404);
            }

            $userClubId = (int) ($user->club?->id ?? 0);
            $bidClubId = (int) ($this->resolveClubProfile($bid->created_club_id)?->id ?? 0);

            if ($bidClubId !== $userClubId) {
                return $this->validationError(null, 'You are not allowed to update this bid.', 403);
            }

            $status = (string) $request->status;
            $notifiedBidIds = [$bid->id];

            DB::transaction(function () use ($bid, $status, &$notifiedBidIds): void {
                $bid->update(['status' => $status]);

                if ($status === 'accepted') {
                    $otherBidIds = MatchBid::query()
                        ->where('match_id', $bid->match_id)
                        ->where('id', '!=', $bid->id)
                        ->pluck('id')
                        ->all();

                    if (! empty($otherBidIds)) {
                        MatchBid::query()
                            ->whereIn('id', $otherBidIds)
                            ->update(['status' => 'rejected']);

                        $notifiedBidIds = array_merge($notifiedBidIds, $otherBidIds);
                    }
                }
            });

            $notifiedBids = MatchBid::with(['requestedClub.user'])->whereIn('id', array_values(array_unique($notifiedBidIds)))->get();

            foreach ($notifiedBids as $notifiedBid) {
                if ($notifiedBid->requestedClub?->user?->email) {
                    SendMatchBidStatusMailJob::dispatchSync($notifiedBid->id);
                }
            }

            return $this->success(
                $this->formatMatchBid($bid->fresh([
                    'match.clubTeam.club.club.user.clubTeams.competitionLevel',
                    'match.opponentClub.user.clubTeams.competitionLevel',
                    'createdClub.user.clubTeams.competitionLevel',
                    'requestedClub.user.clubTeams.competitionLevel',
                ])),
                $status === 'accepted' ? 'Bid accepted successfully.' : 'Bid rejected successfully.',
                200
            );
        } catch (\Throwable $e) {
            return $this->error(null, 'Failed to update bid status: ' . $e->getMessage(), 500);
        }
    }
}
