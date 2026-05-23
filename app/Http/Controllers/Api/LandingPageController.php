<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeBaner;
use App\Models\StatsSection;
use App\Models\HowWorks;
use App\Models\FeatureDetails;
use App\Models\FeatureCart;
use App\Models\TrustedReview;
use App\Models\LandingEcosystem;
use App\Models\CoachSection;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    public function index()
    {
        try {
            $data = [
                'hero' => HomeBaner::first() ? HomeBaner::all()->map(function ($item) {
                    $item->baner_image = $item->baner_image ? asset($item->baner_image) : null;
                    $item->logo_image = $item->logo_image ? asset($item->logo_image) : null;
                    return $item;
                })->first() : (object)[],
                'stats' => StatsSection::first() ?: (object)[],
                'how_it_works' => [
                    'steps' => HowWorks::all()->map(function ($item) {
                        $item->image = $item->image ? asset($item->image) : null;
                        return $item;
                    }) ?: [],
                ],
                'features' => [
                    'header' => FeatureDetails::first() ?: (object)[],
                    'items' => FeatureCart::all()->map(function ($item) {
                        $item->icon = $item->icon ? asset($item->icon) : null;

                        return $item;
                    }) ?: [],
                ],
                'ecosystem' => [
                    'header' => LandingEcosystem::where('type', 'header')->first() ?: (object)[],
                    'cards' => LandingEcosystem::where('type', 'card')->get() ?: [],
                ],
                'reviews' => [
                    'header' => CoachSection::first() ?: (object)[],
                    'items' => TrustedReview::all()->map(function ($item) {
                        $item->user_image = $item->user_image ? asset($item->user_image) : null;
                        return $item;
                    }) ?: [],
                ],
            ];

            return response()->json([
                'status' => true,
                'message' => 'Landing page data fetched successfully',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
