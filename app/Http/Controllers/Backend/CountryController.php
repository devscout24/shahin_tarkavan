<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CountryController extends Controller
{
    public function index(): View
    {
        return view('Backend.settings.country');
    }

    public function data(): JsonResponse
    {
        $countries = Country::query()->latest();

        return DataTables::eloquent($countries)
            ->addColumn('status_badge', function (Country $country) {
                $badgeClass = $country->status === 'active' ? 'bg-success' : 'bg-secondary';
                return '<span class="badge ' . $badgeClass . '">' . ucfirst($country->status) . '</span>';
            })
            ->addColumn('action', function (Country $country) {
                return '<div class="d-flex gap-1">'
                    . '<button type="button" class="btn btn-sm btn-icon btn-primary js-edit-country" data-id="' . $country->id . '" title="Edit"><i class="bi bi-pencil-square text-white"></i></button>'
                    . '<button type="button" class="btn btn-sm btn-icon btn-danger js-delete-country" data-id="' . $country->id . '" title="Delete"><i class="bi bi-trash text-white"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:countries,name'],
            'iso_code' => ['nullable', 'string', 'max:10'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $country = Country::query()->create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Country created successfully.',
            'data' => ['id' => $country->id],
        ]);
    }

    public function edit(Country $country): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'id' => $country->id,
                'name' => $country->name,
                'iso_code' => $country->iso_code,
                'status' => $country->status,
            ],
        ]);
    }

    public function update(Request $request, Country $country): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('countries', 'name')->ignore($country->id)],
            'iso_code' => ['nullable', 'string', 'max:10'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $country->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Country updated successfully.',
        ]);
    }

    public function destroy(Country $country): JsonResponse
    {
        $country->delete();

        return response()->json([
            'status' => true,
            'message' => 'Country deleted successfully.',
        ]);
    }
}
