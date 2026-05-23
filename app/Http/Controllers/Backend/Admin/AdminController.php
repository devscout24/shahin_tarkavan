<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Coach;
use App\Models\AthleteProfiles;
use App\Models\ClubProfile;
use App\Models\ClubMatch;
use App\Models\BookingDateAndTime;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function adminDashboard()
    {
        $data = [
            'total_users' => User::count(),
            'total_coaches' => Coach::count(),
            'total_athletes' => AthleteProfiles::count(),
            'total_clubs' => ClubProfile::count(),
            'total_bookings' => BookingDateAndTime::count(),
            'total_matches' => ClubMatch::count(),
            'recent_users' => User::latest()->take(5)->get(),
        ];

        return view('Backend.Layouts.Dashboard.adminDashboard', compact('data'));
    }
}
