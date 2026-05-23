<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\Coach;
use App\Models\PlayerVotingSyatem;
use App\Models\Setting;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PlayerVotingController extends Controller
{
    use ApiResponse;

    private const DEFAULT_MONTHLY_VOTE_LIMITS = [
        'provencial' => 7,
        'professional' => 12,
    ];

    private function resolveMonthlyLimit(string $voteType): int
    {
        $defaultLimit = self::DEFAULT_MONTHLY_VOTE_LIMITS[$voteType] ?? 0;
        if ($defaultLimit <= 0) {
            return 0;
        }

        $settingKey = $voteType . '_monthly_limit';
        $settingValue = Setting::getValue('voting', $settingKey, (string) $defaultLimit);
        $resolved = (int) $settingValue;

        return $resolved > 0 ? $resolved : $defaultLimit;
    }

    private function monthlyVoteQuotaData($owner, string $voteType, Carbon $monthStart, Carbon $monthEnd): array
    {
        $limit = $this->resolveMonthlyLimit($voteType);

        $query = PlayerVotingSyatem::query()
            ->where('vote_type', $voteType)
            ->whereBetween('created_at', [$monthStart, $monthEnd]);

        if (in_array($owner['role'], ['player', 'parent'], true)) {
            $query->where('player_id', $owner['player']?->id);
        } else {
            $query->where('coach_id', $owner['coach']?->id);
        }

        $used = (int) $query->count();

        return [
            'monthly_limit' => $limit,
            'used_this_month' => $used,
            'remaining_this_month' => max(0, $limit - $used),
        ];
    }

    private function resolveVoteOwnerByRole($user, ?Request $request = null): array
    {
        $role = (string) ($user->role ?? '');

        if ($role === 'player') {
            $player = AthleteProfiles::query()->where('user_id', $user->id)->first();
            if (! $player) {
                return ['error' => $this->errors([], 'Player profile not found.', 404)];
            }

            return [
                'role' => 'player',
                'player' => $player,
                'coach' => null,
            ];
        }

        if ($role === 'coach') {
            $coach = Coach::query()->where('user_id', $user->id)->first();
            if (! $coach) {
                return ['error' => $this->errors([], 'Coach profile not found for this user.', 404)];
            }

            return [
                'role' => 'coach',
                'player' => null,
                'coach' => $coach,
            ];
        }

        if ($role === 'parent') {
            $childId = (int) ($request?->integer('child_id', 0) ?? 0);
            if ($childId <= 0) {
                return ['error' => $this->errors(['child_id' => ['child_id is required for parent voting.']], 'Validation failed', 422)];
            }

            $child = AthleteProfiles::query()
                ->where('id', $childId)
                ->where('parent_id', $user->id)
                ->first();

            if (! $child) {
                return ['error' => $this->errors([], 'Selected child is not under this parent.', 403)];
            }

            return [
                'role' => 'parent',
                'player' => $child,
                'coach' => null,
            ];
        }

        return ['error' => $this->errors([], 'Only coach, player, or parent can vote.', 403)];
    }

    public function vote(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'vote_for_player_id' => 'required',
            'vote_type' => ['nullable', Rule::in(['provencial', 'professional'])],
            'child_id' => ['nullable', 'integer', 'exists:athlete_profiles,id'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        $user = Auth::guard('api')->user();
        if (! $user) {
            return $this->errors([], 'Authentication required.', 401);
        }

        $owner = $this->resolveVoteOwnerByRole($user, $request);
        if (isset($owner['error'])) {
            return $owner['error'];
        }

        $voterProfile = $owner['player'];
        $coach = $owner['coach'];

        $targetPlayer = AthleteProfiles::query()->find($request->integer('vote_for_player_id'));
        if (! $targetPlayer) {
            return $this->errors([], 'Target player not found.', 404);
        }

        $voteType = trim((string) $request->input('vote_type', 'provencial'));
        if ($voteType === '') {
            $voteType = 'provencial';
        }

        if (in_array($owner['role'], ['player', 'parent'], true)) {
            if ((int) $voterProfile->id === (int) $targetPlayer->id) {
                return $this->errors([], 'You cannot vote for yourself.', 422);
            }
        }

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        if (in_array($owner['role'], ['player', 'parent'], true)) {
            $monthlyLimit = $this->resolveMonthlyLimit($voteType);
            $votesGivenThisMonth = PlayerVotingSyatem::query()
                ->where('player_id', $voterProfile?->id)
                ->where('vote_type', $voteType)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->with(['voteForPlayer:id,name,last_name,image'])
                ->latest('id')
                ->get();

            $alreadyVotedForTarget = $votesGivenThisMonth
                ->contains(fn(PlayerVotingSyatem $vote) => (int) $vote->vote_for_player_id === (int) $targetPlayer->id);

            if (! $alreadyVotedForTarget && $votesGivenThisMonth->count() >= $monthlyLimit) {
                return $this->errors([
                    'limit' => 'Monthly vote limit reached for this type.',
                    'monthly_limit' => $monthlyLimit,
                    'used_this_month' => $votesGivenThisMonth->count(),
                    'remaining_this_month' => 0,
                    'given_votes' => $votesGivenThisMonth->map(function (PlayerVotingSyatem $vote) {
                        return [
                            'id' => $vote->id,
                            'vote_for_player_id' => $vote->vote_for_player_id,
                            'vote_for_player_name' => trim((string) ($vote->voteForPlayer?->name ?? '') . ' ' . (string) ($vote->voteForPlayer?->last_name ?? '')),
                            'vote_for_player_image' => ! empty($vote->voteForPlayer?->image) ? asset($vote->voteForPlayer->image) : null,
                            'vote_type' => $vote->vote_type,
                            'created_at' => optional($vote->created_at)?->toDateTimeString(),
                        ];
                    })->values(),
                ], 'Monthly vote limit reached for this type. Delete one vote to cast another.', 422);
            }
        }

        $existingVoteQuery = PlayerVotingSyatem::query()
            ->where('vote_for_player_id', $targetPlayer->id)
            ->where('vote_type', $voteType)
            ->whereBetween('created_at', [$monthStart, $monthEnd]);

        if ($voterProfile?->id) {
            $existingVoteQuery->where('player_id', $voterProfile->id);
        } else {
            $existingVoteQuery->whereNull('player_id');
        }

        if ($coach?->id) {
            $existingVoteQuery->where('coach_id', $coach->id);
        } else {
            $existingVoteQuery->whereNull('coach_id');
        }

        $vote = $existingVoteQuery->latest('id')->first();


     $vote = $existingVoteQuery->latest('id')->first();
        if ($vote) {
            return $this->success([], 'You have already voted for this player this month.', 422);
        }


        if (! $vote) {
            $vote = PlayerVotingSyatem::query()->create([
                'player_id' => $voterProfile?->id,
                'vote_for_player_id' => $targetPlayer->id,
                'coach_id' => $coach?->id,
                'vote_type' => $voteType,
                'voted' => true,
            ]);
        }

        if (! $vote->voted) {
            $vote->voted = true;
            $vote->save();
        }

        $quota = $this->monthlyVoteQuotaData($owner, $voteType, $monthStart, $monthEnd);

        return $this->success([
            'id' => $vote->id,
            'player_id' => $vote->player_id,
            'vote_for_player_id' => $vote->vote_for_player_id,
            'coach_id' => $vote->coach_id,
            'vote_type' => $vote->vote_type,
            'voted' => (bool) $vote->voted,
            'monthly_quota' => $quota,
        ], 'Vote submitted successfully.', 200);
    }

    public function givenVotes(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (! $user) {
            return $this->errors([], 'Authentication required.', 401);
        }

        $owner = $this->resolveVoteOwnerByRole($user, $request);
        if (isset($owner['error'])) {
            return $owner['error'];
        }

        $voterProfile = $owner['player'];
        $coach = $owner['coach'];

        $voteType = trim((string) $request->input('vote_type', 'provencial'));
        if ($voteType === '') {
            $voteType = 'provencial';
        }

        $query = PlayerVotingSyatem::query()
            ->where('vote_type', $voteType)
            ->with([
                'voteForPlayer:id,name,last_name,image',
                'coach:id,name,last_name',
            ])
            ->latest('id');

        if (in_array($owner['role'], ['player', 'parent'], true)) {
            $query->where('player_id', $voterProfile->id);
        } else {
            $query->where('coach_id', $coach->id);
        }

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        if ($request->boolean('current_month', true)) {
            $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
        }

        $votes = $query->get()->map(function (PlayerVotingSyatem $vote) {
            return [
                'id' => $vote->id,
                'vote_type' => $vote->vote_type,
                'voted' => (bool) $vote->voted,
                'coach' => [
                    'id' => $vote->coach?->id,
                    'name' => trim((string) ($vote->coach?->name ?? '') . ' ' . (string) ($vote->coach?->last_name ?? '')),
                ],
                'vote_for_player' => [
                    'id' => $vote->voteForPlayer?->id,
                    'name' => trim((string) ($vote->voteForPlayer?->name ?? '') . ' ' . (string) ($vote->voteForPlayer?->last_name ?? '')),
                    'image' => ! empty($vote->voteForPlayer?->image) ? asset($vote->voteForPlayer->image) : null,
                ],
                'created_at' => optional($vote->created_at)?->toDateTimeString(),
            ];
        })->values();

        $quota = $this->monthlyVoteQuotaData($owner, $voteType, $monthStart, $monthEnd);

        return $this->success([
            'vote_type' => $voteType,
            'total' => $votes->count(),
            'monthly_quota' => $quota,
            'votes' => $votes,
        ], 'Given votes fetched successfully.', 200);
    }

    public function receivedVotes(Request $request)
    {
        $user = Auth::guard('api')->user();

       if($user->role == 'parent'){
            $userId = AthleteProfiles::query()->where('id', $request->integer('child_id'))->first();
        }

        else{
            $userId = $user->id;
        }

        if (! $user) {
            return $this->errors([], 'Authentication required.', 401);
        }

        $playerId = (int) $request->integer('player_id', 0);
        if ($playerId <= 0) {
            $profile = AthleteProfiles::query()->where('user_id', $userId)->first();
            if (! $profile) {
                return $this->errors([], 'Player profile not found.', 404);
            }
            $playerId = (int) $profile->id;
        }

     $provencialCount = PlayerVotingSyatem::query()
                    ->where('vote_for_player_id', $playerId)
                    ->where('vote_type', 'provencial')
                    ->count();

                    $professionalCount = PlayerVotingSyatem::query()
                    ->where('vote_for_player_id', $playerId)
                    ->where('vote_type', 'professional')
                    ->count();

        $query = PlayerVotingSyatem::query()
            ->where('vote_for_player_id', $playerId)

            ->with([
                'player:id,name,last_name,image',
                'coach:id,name,last_name',
            ])
            ->latest('id');

        if ($request->boolean('current_month', true)) {
            $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
        }

        $votes = $query->get()->map(function (PlayerVotingSyatem $vote) {
            return [
                'id' => $vote->id,
                'vote_type' => $vote->vote_type,
                'voted' => (bool) $vote->voted,
                'voter_player' => [
                    'id' => $vote->player?->id,
                    'name' => trim((string) ($vote->player?->name ?? '') . ' ' . (string) ($vote->player?->last_name ?? '')),
                    'image' => ! empty($vote->player?->image) ? asset($vote->player->image) : null,
                ],
                'coach' => [
                    'id' => $vote->coach?->id,
                    'name' => trim((string) ($vote->coach?->name ?? '') . ' ' . (string) ($vote->coach?->last_name ?? '')),
                ],
                'created_at' => optional($vote->created_at)?->toDateTimeString(),
            ];
        })->values();

        return $this->success([
            'player_id' => $playerId,
            'provencial_votes' => $provencialCount,
            'professional_votes' => $professionalCount,
            'total' => $votes->count(),
            'votes' => $votes,
        ], 'Received votes fetched successfully.', 200);
    }

    public function deleteVote(Request $request, int $vote_id)
    {
        $user = Auth::guard('api')->user();
        if (! $user) {
            return $this->errors([], 'Authentication required.', 401);
        }

        $owner = $this->resolveVoteOwnerByRole($user, $request);
        if (isset($owner['error'])) {
            return $owner['error'];
        }

        $voterProfile = $owner['player'];
        $coach = $owner['coach'];

        $voteQuery = PlayerVotingSyatem::query()->where('id', $vote_id);
        if (in_array($owner['role'], ['player', 'parent'], true)) {
            $voteQuery->where('player_id', $voterProfile->id);
        } else {
            $voteQuery->where('coach_id', $coach->id);
        }
        $vote = $voteQuery->first();

        if (! $vote) {
            return $this->errors([], 'Vote not found.', 404);
        }

        $vote->delete();

        return $this->success([], 'Vote deleted successfully. You can vote for another player now.', 200);
    }
}
