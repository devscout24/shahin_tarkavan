<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CompetitionLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CompetitionLevelController extends Controller
{
    public function index(): View
    {
        return view('Backend.competitions.levels');
    }

    public function data(): JsonResponse
    {
        $levels = CompetitionLevel::query()->latest();

        return DataTables::eloquent($levels)
            ->addColumn('status_badge', function (CompetitionLevel $level) {
                $badgeClass = $level->status === 'active' ? 'bg-success' : 'bg-secondary';

                return '<span class="badge ' . $badgeClass . '">' . ucfirst($level->status) . '</span>';
            })
            ->addColumn('action', function (CompetitionLevel $level) {
                return '<div class="d-flex gap-1">'
                    . '<button type="button" class="btn btn-sm btn-primary js-edit-level" data-id="' . $level->id . '">Edit</button>'
                    . '<button type="button" class="btn btn-sm btn-danger js-delete-level" data-id="' . $level->id . '">Delete</button>'
                    . '</div>';
            })
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:competition_levels,name'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $competitionLevel = CompetitionLevel::query()->create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Competition level created successfully.',
            'data' => [
                'id' => $competitionLevel->id,
            ],
        ]);
    }

    public function edit(CompetitionLevel $competitionLevel): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'id' => $competitionLevel->id,
                'name' => $competitionLevel->name,
                'status' => $competitionLevel->status,
            ],
        ]);
    }

    public function update(Request $request, CompetitionLevel $competitionLevel): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('competition_levels', 'name')->ignore($competitionLevel->id),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $competitionLevel->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Competition level updated successfully.',
        ]);
    }

    public function destroy(CompetitionLevel $competitionLevel): JsonResponse
    {
        $competitionLevel->delete();

        return response()->json([
            'status' => true,
            'message' => 'Competition level deleted successfully.',
        ]);
    }
}
