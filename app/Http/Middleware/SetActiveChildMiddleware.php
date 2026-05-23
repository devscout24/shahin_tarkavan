<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AthleteProfiles;
use Illuminate\Support\Facades\Auth;

class SetActiveChildMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $childId = $request->header('active-child-id');
        $user = Auth::guard('api')->user();

        if ($user && $childId) {
            // Verify if the child belongs to the parent to prevent unauthorized access
            $isOwnChild = AthleteProfiles::where('id', $childId)
                ->where('parent_id', $user->id)
                ->exists();

            if ($isOwnChild) {
                // Store the active child ID in the request attributes for easy access
                $request->attributes->set('active_child_id', $childId);
            }
        }

        return $next($request);
    }
}
