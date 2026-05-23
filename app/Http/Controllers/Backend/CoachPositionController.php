<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CoachPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CoachPositionController extends Controller
{
    public function index(): View
    {
        return view('Backend.coaches.positions');
    }

    public function data(): JsonResponse
    {
        $positions = CoachPosition::query()->latest();

        return DataTables::eloquent($positions)
            ->addColumn('status_badge', function (CoachPosition $position) {
                $badgeClass = $position->status === 'active' ? 'bg-success' : 'bg-secondary';

                return '<span class="badge ' . $badgeClass . '">' . ucfirst($position->status) . '</span>';
            })
            ->addColumn('action', function (CoachPosition $position) {
                return '<div class="d-flex gap-1">'
                    . '<button type="button" class="btn btn-sm btn-icon btn-primary js-edit-position" data-id="' . $position->id . '" title="Edit"><i class="bi bi-pencil-square text-white"></i></button>'
                    . '<button type="button" class="btn btn-sm btn-icon btn-danger js-delete-position" data-id="' . $position->id . '" title="Delete"><i class="bi bi-trash text-white"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:coach_positions,name'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $position = CoachPosition::query()->create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Coach position created successfully.',
            'data' => [
                'id' => $position->id,
            ],
        ]);
    }

    public function edit(CoachPosition $coachPosition): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'id' => $coachPosition->id,
                'name' => $coachPosition->name,
                'status' => $coachPosition->status,
            ],
        ]);
    }

    public function update(Request $request, CoachPosition $coachPosition): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('coach_positions', 'name')->ignore($coachPosition->id),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $coachPosition->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Coach position updated successfully.',
        ]);
    }

    public function destroy(CoachPosition $coachPosition): JsonResponse
    {
        $coachPosition->delete();

        return response()->json([
            'status' => true,
            'message' => 'Coach position deleted successfully.',
        ]);
    }
}
