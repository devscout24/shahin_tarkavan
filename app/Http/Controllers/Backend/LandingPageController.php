<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\HomeBaner;
use App\Models\StatsSection;
use App\Models\HowWork;
use App\Models\FeatureDetail;
use App\Models\FeatureCart;
use App\Models\TrustedReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LandingPageController extends Controller
{
    public function hero()
    {
        $hero = HomeBaner::first();
        return view('Backend.landing-page.hero', compact('hero'));
    }

    public function updateHero(Request $request)
    {
        $request->validate([
            'baner_title' => 'required|string',
            'baner_description' => 'required|string',
            'baner_image' => 'nullable',
            'logo_image' => 'nullable',
        ]);

        $hero = HomeBaner::first() ?? new HomeBaner();
        $hero->baner_title = $request->baner_title;
        $hero->baner_description = $request->baner_description;

        if ($request->hasFile('baner_image')) {
            if ($hero->baner_image && file_exists(public_path($hero->baner_image))) {
                unlink(public_path($hero->baner_image));
            }
            $imageName = time() . '_baner.' . $request->baner_image->extension();
            $request->baner_image->move(public_path('uploads/landing'), $imageName);
            $hero->baner_image = 'uploads/landing/' . $imageName;
        }

        if ($request->hasFile('logo_image')) {
            if ($hero->logo_image && file_exists(public_path($hero->logo_image))) {
                unlink(public_path($hero->logo_image));
            }
            $logoName = time() . '_logo.' . $request->logo_image->extension();
            $request->logo_image->move(public_path('uploads/landing'), $logoName);
            $hero->logo_image = 'uploads/landing/' . $logoName;
        }

        $hero->save();

        return back()->with('status', 'Hero section updated successfully!');
    }

    public function stats()
    {
        $stats = StatsSection::first();
        return view('Backend.landing-page.stats', compact('stats'));
    }

    public function updateStats(Request $request)
    {
        $request->validate([
            'active_athletes' => 'required|integer',
            'certified_coaches' => 'required|integer',
            'teams' => 'required|integer',
            'session_booked' => 'required|integer',
        ]);

        $stats = StatsSection::first() ?? new StatsSection();
        $stats->active_athletes = $request->active_athletes;
        $stats->certified_coaches = $request->certified_coaches;
        $stats->teams = $request->teams;
        $stats->session_booked = $request->session_booked;
        $stats->save();

        return back()->with('status', 'Stats updated successfully!');
    }

    public function howItWorks()
    {
        $howWorks = HowWork::all();
        return view('Backend.landing-page.how_it_works', compact('howWorks'));
    }

    public function updateHowItWorks(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'image' => 'nullable',
        ]);

        $howWork = new HowWork();
        $howWork->title = $request->title;
        $howWork->description = $request->description;

        if ($request->hasFile('image')) {
            $imageName = time() . '_how.' . $request->image->extension();
            $request->image->move(public_path('uploads/landing'), $imageName);
            $howWork->image = 'uploads/landing/' . $imageName;
        }

        $howWork->save();

        return back()->with('status', 'Step added successfully!');
    }

    public function features()
    {
        $detail = FeatureDetail::first();
        $features = FeatureCart::all();
        return view('Backend.landing-page.features', compact('detail', 'features'));
    }

    public function storeFeature(Request $request)
    {
        if ($request->has('update_detail')) {
            $request->validate([
                'title' => 'required|string',
                'description' => 'required|string',
            ]);
            $detail = FeatureDetail::first() ?? new FeatureDetail();
            $detail->title = $request->title;
            $detail->description = $request->description;
            $detail->save();
            return back()->with('status', 'Feature heading updated!');
        }

        $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'icon' => 'required',
        ]);

        $detail = FeatureDetail::first();
        if (!$detail) {
            return back()->with('error', 'Please first update the Feature Section Heading.');
        }

        $feature = new FeatureCart();
        $feature->feature_id = $detail->id;
        $feature->title = $request->title;
        $feature->description = $request->description;

        if ($request->hasFile('icon')) {
            $imageName = time() . '_feature.' . $request->icon->extension();
            $request->icon->move(public_path('uploads/landing'), $imageName);
            $feature->icon = 'uploads/landing/' . $imageName;
        }

        $feature->save();

        return back()->with('status', 'Feature card added successfully!');
    }

    public function reviews()
    {
        $reviews = TrustedReview::all();
        return view('Backend.landing-page.reviews', compact('reviews'));
    }

    public function storeReview(Request $request)
    {
        $request->validate([
            'user_name' => 'required|string',
            'user_designation' => 'required|string',
            'review_text' => 'required|string',
            'rating' => 'required',
            'user_image' => 'nullable',
        ]);

        $review = new TrustedReview();
        $review->user_name = $request->user_name;
        $review->user_designation = $request->user_designation;
        $review->review_text = $request->review_text;
        $review->rating = $request->rating;

        if ($request->hasFile('user_image')) {
            $imageName = time() . '_user.' . $request->user_image->extension();
            $request->user_image->move(public_path('uploads/landing'), $imageName);
            $review->user_image = 'uploads/landing/' . $imageName;
        }

        $review->save();

        return back()->with('status', 'Review added successfully!');
    }
}