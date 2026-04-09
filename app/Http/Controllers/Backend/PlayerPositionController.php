<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PlayerPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PlayerPositionController extends Controller
{
    public function index(): View
    {
        return view('Backend.players.positions');
    }

    public function data(): JsonResponse
    {
        $positions = PlayerPosition::query()->latest();

        return DataTables::eloquent($positions)
            ->addColumn('status_badge', function (PlayerPosition $position) {
                $badgeClass = $position->status === 'active' ? 'bg-success' : 'bg-secondary';

                return '<span class="badge ' . $badgeClass . '">' . ucfirst($position->status) . '</span>';
            })
            ->addColumn('action', function (PlayerPosition $position) {
                return '<div class="d-flex gap-1">'
                    . '<button type="button" class="btn btn-sm btn-primary js-edit-position" data-id="' . $position->id . '">Edit</button>'
                    . '<button type="button" class="btn btn-sm btn-danger js-delete-position" data-id="' . $position->id . '">Delete</button>'
                    . '</div>';
            })
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:player_positions,name'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $position = PlayerPosition::query()->create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Player position created successfully.',
            'data' => [
                'id' => $position->id,
            ],
        ]);
    }

    public function edit(PlayerPosition $playerPosition): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'id' => $playerPosition->id,
                'name' => $playerPosition->name,
                'status' => $playerPosition->status,
            ],
        ]);
    }

    public function update(Request $request, PlayerPosition $playerPosition): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('player_positions', 'name')->ignore($playerPosition->id),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $playerPosition->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Player position updated successfully.',
        ]);
    }

    public function destroy(PlayerPosition $playerPosition): JsonResponse
    {
        $playerPosition->delete();

        return response()->json([
            'status' => true,
            'message' => 'Player position deleted successfully.',
        ]);
    }
}
