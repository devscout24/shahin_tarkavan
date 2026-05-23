<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\OrganizationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class OrganizationTypeController extends Controller
{
    public function index(): View
    {
        return view('Backend.settings.organization_type');
    }

    public function data(): JsonResponse
    {
        $types = OrganizationType::query()->latest();

        return DataTables::eloquent($types)
            ->addColumn('status_badge', function (OrganizationType $type) {
                $badgeClass = $type->status === 'active' ? 'bg-success' : 'bg-secondary';
                return '<span class="badge ' . $badgeClass . '">' . ucfirst($type->status) . '</span>';
            })
            ->addColumn('action', function (OrganizationType $type) {
                return '<div class="d-flex gap-1">'
                    . '<button type="button" class="btn btn-sm btn-icon btn-primary js-edit-type" data-id="' . $type->id . '" title="Edit"><i class="bi bi-pencil-square text-white"></i></button>'
                    . '<button type="button" class="btn btn-sm btn-icon btn-danger js-delete-type" data-id="' . $type->id . '" title="Delete"><i class="bi bi-trash text-white"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:organization_types,name'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $organizationType = OrganizationType::query()->create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Organization type created successfully.',
            'data' => [
                'id' => $organizationType->id,
            ],
        ]);
    }

    public function edit(OrganizationType $organizationType): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'id' => $organizationType->id,
                'name' => $organizationType->name,
                'status' => $organizationType->status,
            ],
        ]);
    }

    public function update(Request $request, OrganizationType $organizationType): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('organization_types', 'name')->ignore($organizationType->id),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $organizationType->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Organization type updated successfully.',
        ]);
    }

    public function destroy(OrganizationType $organizationType): JsonResponse
    {
        $organizationType->delete();

        return response()->json([
            'status' => true,
            'message' => 'Organization type deleted successfully.',
        ]);
    }
}
