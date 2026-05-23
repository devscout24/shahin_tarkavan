<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SportOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class SportOptionController extends Controller
{
    public function index(): View
    {
        return view('Backend.sports.options');
    }

    public function data(Request $request): JsonResponse
    {
        $options = SportOption::query()
            ->when($request->filled('audience'), function ($query) use ($request): void {
                $query->where('audience', $request->string('audience'));
            })
            ->latest();

        return DataTables::eloquent($options)
            ->addColumn('audience_badge', function (SportOption $option) {
                $badgeClass = $option->audience === 'player' ? 'bg-primary' : 'bg-info';

                return '<span class="badge ' . $badgeClass . '">' . ucfirst($option->audience) . '</span>';
            })
            ->addColumn('status_badge', function (SportOption $option) {
                $badgeClass = $option->status === 'active' ? 'bg-success' : 'bg-secondary';

                return '<span class="badge ' . $badgeClass . '">' . ucfirst($option->status) . '</span>';
            })
            ->addColumn('action', function (SportOption $option) {
                return '<div class="d-flex gap-1">'
                    . '<button type="button" class="btn btn-sm btn-icon btn-primary js-edit-option" data-id="' . $option->id . '" title="Edit"><i class="bi bi-pencil-square text-white"></i></button>'
                    . '<button type="button" class="btn btn-sm btn-icon btn-danger js-delete-option" data-id="' . $option->id . '" title="Delete"><i class="bi bi-trash text-white"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['audience_badge', 'status_badge', 'action'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'audience' => ['required', Rule::in(['player', 'coach'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $option = SportOption::query()->create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Sport option created successfully.',
            'data' => [
                'id' => $option->id,
            ],
        ]);
    }

    public function edit(SportOption $sportOption): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'id' => $sportOption->id,
                'name' => $sportOption->name,
                'audience' => $sportOption->audience,
                'status' => $sportOption->status,
            ],
        ]);
    }

    public function update(Request $request, SportOption $sportOption): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'audience' => ['required', Rule::in(['player', 'coach'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $duplicateExists = SportOption::query()
            ->where('audience', $validated['audience'])
            ->where('name', $validated['name'])
            ->where('id', '!=', $sportOption->id)
            ->exists();

        if ($duplicateExists) {
            return response()->json([
                'status' => false,
                'message' => 'This sport option already exists for the selected audience.',
            ], 422);
        }

        $sportOption->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Sport option updated successfully.',
        ]);
    }

    public function destroy(SportOption $sportOption): JsonResponse
    {
        $sportOption->delete();

        return response()->json([
            'status' => true,
            'message' => 'Sport option deleted successfully.',
        ]);
    }
}
