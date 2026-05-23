<?php

namespace App\Http\Middleware;

use App\Models\ClubSubscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveClubSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        // Only enforce this check for authenticated club accounts.
        if (! $user || $user->role !== 'club') {
            return $next($request);
        }

        $hasActiveSubscription = ClubSubscription::query()
            ->where('club_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $hasActiveSubscription) {
            return response()->json([
                'status' => false,
                'message' => 'Active club subscription not found.',
                'errors' => [],
            ], 403);
        }

        return $next($request);
    }
}
