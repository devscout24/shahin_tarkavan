<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ErProgram;
use App\Support\AgeGroup;
use App\Traits\ProgramProviderTrait;
use Illuminate\Http\Request;

class ProgramDetailController extends Controller
{
    use ProgramProviderTrait;

    public function show($id)
    {
        try {
            $program = ErProgram::with([
                'coach',
                'user.club',
                'times',
                'goals',
                'sportOption:id,name',
                'reviews.user:id,name,last_name,profile_image'
            ])->findOrFail($id);

            $data = $this->formatProgramData($program);

            // Add reviews summary and recent feedback which are specific to show()
            $data['reviews_summary'] = [
                'average_rating' => (float) ($program->reviews()->avg('rating') ?? 0),
                'total_reviews' => $program->reviews()->count(),
                'rating_distribution' => [
                    '5' => $program->reviews()->where('rating', 5)->count(),
                    '4' => $program->reviews()->where('rating', 4)->count(),
                    '3' => $program->reviews()->where('rating', 3)->count(),
                    '2' => $program->reviews()->where('rating', 2)->count(),
                    '1' => $program->reviews()->where('rating', 1)->count()
                ]
            ];

            $data['recent_feedback'] = ($program->reviews ?? collect())->take(5)->map(function ($review) {
                return [
                    'user_name' => $review->user->name ?? 'Anonymous',
                    'user_image' => ($review->user && $review->user->profile_image) ? asset($review->user->profile_image) : null,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'date' => $review->created_at->format('M d, Y')
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Program details fetched successfully',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}