<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    use ApiResponse;

    public function countries()
    {
        $countries = Country::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'iso_code']);

        return $this->success($countries, 'Country list fetched successfully', 200);
    }

    public function cities(Request $request)
    {
        $countryId = $request->input('country_id');

        $cities = City::query()
            ->where('status', 'active')
            ->when($countryId, function ($query) use ($countryId) {
                $query->where('country_id', $countryId);
            })
            ->orderBy('name')
            ->get(['id', 'country_id', 'name']);

        return $this->success($cities, 'City list fetched successfully', 200);
    }
}
