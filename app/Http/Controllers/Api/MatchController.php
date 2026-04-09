<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubMatch;
use App\Models\ClubSubscription;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MatchController extends Controller
{
    use ApiResponse;
    public function updateCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'club_team_id' => 'required|exists:club_teams,id',
            'available_date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'field_opportunity' => 'nullable|string|max:255',
            'match_id' => 'nullable|exists:club_matches,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), 'Validation failed', 422);
        }

        try {

            $club_subscription = ClubSubscription::query()
            ->where('club_id', Auth::guard('api')->user()->id)
            ->where('status', 'active')
            ->first();
        if (!$club_subscription) {
            return $this->errors([], 'Your subscription has expired. Please renew to create or update
                a match.', 403);
        }

           $match= ClubMatch::query()
                ->where('id', $request->input('match_id'))
                ->first();
            if(!$match){
                $match = new ClubMatch();
            }
        $match->club_team_id = $request->input('club_team_id');
        $match->available_date = $request->input('available_date');
        $match->location = $request->input('location');
        $match->field_opportunity = $request->input('field_opportunity');

        $match->save();
        return $this->success($match, 'Match created successfully', 200);
        }

        catch(\Throwable $e){
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function show($match_id)
    {

       $club_subscription = ClubSubscription::query()
            ->where('club_id', Auth::guard('api')->user()->id)
            ->where('status', 'active')
            ->first();
        if (!$club_subscription) {
            return $this->errors([], 'Your subscription has expired. Please renew to create or update
                a match.', 403);
        }



        try {
            $match = ClubMatch::query()
                ->where('id', $match_id)
                ->with(['clubTeam:id,name'])
                ->first();

            if (! $match) {
                return $this->notFound([], 'Match not found.', 404);
            }

            return $this->success($match, 'Match details fetched successfully', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function list()
    {

              $club_subscription = ClubSubscription::query()
                    ->where('club_id', Auth::guard('api')->user()->id)
                    ->where('status', 'active')
                    ->first();
                if (!$club_subscription) {
                    return $this->errors([], 'Your subscription has expired. Please renew to create or update
                        a match.', 403);
                }

        try {
            $matches = ClubMatch::query()
                ->with(['clubTeam:id,name'])
                ->orderBy('available_date', 'desc')
                ->get();

            return $this->success($matches, 'Matches fetched successfully', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function delete($match_id)
    {

              $club_subscription = ClubSubscription::query()
                    ->where('club_id', Auth::guard('api')->user()->id)
                    ->where('status', 'active')
                    ->first();
                if (!$club_subscription) {
                    return $this->errors([], 'Your subscription has expired. Please renew to create or update
                        a match.', 403);
                }

        try {
            $match = ClubMatch::query()
                ->where('id', $match_id)
                ->first();

            if (! $match) {
                return $this->notFound([], 'Match not found.', 404);
            }

            $match->delete();

            return $this->success([], 'Match deleted successfully', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }
}