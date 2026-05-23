<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CityController extends Controller
{
    public function index(): View
    {
        $countries = Country::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('Backend.settings.city', compact('countries'));
    }

    public function data(): JsonResponse
    {
        $cities = City::query()->with('country:id,name')->latest();

        return DataTables::eloquent($cities)
            ->addColumn('country_name', function (City $city) {
                return $city->country?->name;
            })
            ->addColumn('status_badge', function (City $city) {
                $badgeClass = $city->status === 'active' ? 'bg-success' : 'bg-secondary';
                return '<span class="badge ' . $badgeClass . '">' . ucfirst($city->status) . '</span>';
            })
            ->addColumn('action', function (City $city) {
                return '<div class="d-flex gap-1">'
                    . '<button type="button" class="btn btn-sm btn-icon btn-primary js-edit-city" data-id="' . $city->id . '" title="Edit"><i class="bi bi-pencil-square text-white"></i></button>'
                    . '<button type="button" class="btn btn-sm btn-icon btn-danger js-delete-city" data-id="' . $city->id . '" title="Delete"><i class="bi bi-trash text-white"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $exists = City::query()
            ->where('country_id', $validated['country_id'])
            ->whereRaw('LOWER(name) = ?', [strtolower((string) $validated['name'])])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'The name has already been taken for this country.',
                'errors' => ['name' => ['The name has already been taken for this country.']],
            ], 422);
        }

        $city = City::query()->create($validated);

        return response()->json([
            'status' => true,
            'message' => 'City created successfully.',
            'data' => ['id' => $city->id],
        ]);
    }

    public function edit(City $city): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'id' => $city->id,
                'country_id' => $city->country_id,
                'name' => $city->name,
                'status' => $city->status,
            ],
        ]);
    }

    public function update(Request $request, City $city): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $exists = City::query()
            ->where('id', '!=', $city->id)
            ->where('country_id', $validated['country_id'])
            ->whereRaw('LOWER(name) = ?', [strtolower((string) $validated['name'])])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'The name has already been taken for this country.',
                'errors' => ['name' => ['The name has already been taken for this country.']],
            ], 422);
        }

        $city->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'City updated successfully.',
        ]);
    }

    public function destroy(City $city): JsonResponse
    {
        $city->delete();

        return response()->json([
            'status' => true,
            'message' => 'City deleted successfully.',
        ]);
    }
}
