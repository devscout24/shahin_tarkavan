<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\CoachPosition;
use App\Models\SportOption;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CoachController extends Controller
{
    public function index(): View
    {
        return view('Backend.coaches.index');
    }

    public function data(): JsonResponse
    {
        $coaches = User::query()
            ->where('role', 'coach')
            ->with(['coachProfile:id,user_id,name,last_name,email,sports,sport_option_id,status', 'coachProfile.sportOption:id,name'])
            ->latest();

        return DataTables::eloquent($coaches)
            ->addColumn('full_name', function (User $user) {
                $profile = $user->coachProfile;
                $name = trim(($profile?->name ?? $user->name ?? '') . ' ' . ($profile?->last_name ?? $user->last_name ?? ''));

                return $name !== '' ? $name : 'N/A';
            })
            ->addColumn('email', fn(User $user) => $user->coachProfile?->email ?? $user->email)
            ->addColumn('sports', function (User $user) {
                $profile = $user->coachProfile;

                return $profile?->sports_display ?? 'N/A';
            })
            ->addColumn('status_badge', function (User $user) {
                $status = $user->coachProfile?->status ?? $user->status ?? 'pending';

                if ($status === 'approve') {
                    return '<span class="badge bg-success">Approved</span>';
                }

                if ($status === 'pending') {
                    return '<span class="badge bg-warning text-dark">Pending</span>';
                }

                return '<span class="badge bg-danger">' . e(ucfirst((string) $status)) . '</span>';
            })
            ->addColumn('action', function (User $user) {
                $profile = $user->coachProfile;

                if (! $profile) {
                    return '<span class="badge bg-secondary">Profile Missing</span>';
                }

                $url = route('admin.coaches.show', $profile);

                return '<a href="' . $url . '" class="btn btn-sm btn-primary">View</a>';
            })
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function show(Coach $coach): View
    {


        $coach->load([
            'user:id,name,last_name,email,status',
            'coachingTitles:id,coach_id,title',
            'media:id,coach_id,image',
            'sportOption:id,name',
            'currentPosition:id,name',
        ]);

        $coach->setAttribute('sports_display', $this->resolveCoachSportName($coach));
        $coach->setAttribute('current_role_display', $this->resolveCoachPositionName($coach));

        return view('Backend.coaches.show', [
            'coach' => $coach,
        ]);
    }

    public function approve(Coach $coach): RedirectResponse
    {
        $coach->status = 'approve';
        $coach->save();

        if ($coach->user) {
            $coach->user->status = 'approve';
            $coach->user->save();
        }

        $targetEmail = $coach->email ?: $coach->user?->email;

        if (! empty($targetEmail)) {
            Mail::raw(
                "Hello {$coach->name},\n\nYour coach profile has been approved by admin. You can now use all coach features.\n\nThanks,\nTarkaven Team",
                function ($message) use ($targetEmail) {
                    $message->to($targetEmail)->subject('Coach Profile Approved');
                }
            );
        }

        return redirect()->route('admin.coaches.show', $coach)->with('status', 'Coach approved and email sent successfully.');
    }

    private function resolveCoachSportName(Coach $coach): ?string
    {


        if ($coach->sportOption?->name) {
            return $coach->sportOption->name;
        }

        $rawSports = trim((string) ($coach->sports ?? ''));

        if ($rawSports === '') {
            return null;
        }

        if (is_numeric($rawSports)) {
            return SportOption::query()->where('id', (int) $rawSports)->value('name') ?: $rawSports;
        }

        return $rawSports;
    }

    private function resolveCoachPositionName(Coach $coach): ?string
    {
        if ($coach->currentPosition?->name) {
            return $coach->currentPosition->name;
        }

        $rawRole = $coach->current_role;

        if (is_null($rawRole) || $rawRole === '') {
            return null;
        }

        if (is_numeric($rawRole)) {
            return CoachPosition::query()->where('id', (int) $rawRole)->value('name') ?: (string) $rawRole;
        }

        return (string) $rawRole;
    }
}

