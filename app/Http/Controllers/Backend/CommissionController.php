<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CommissionController extends Controller
{
    public function index(): View
    {
        return view('Backend.settings.commissions');
    }

    public function data(): JsonResponse
    {
        $commissions = Commission::query()->latest();

        return DataTables::eloquent($commissions)
            ->addColumn('type_badge', function (Commission $commission) {
                $badgeClass = $commission->type === 'percentage' ? 'bg-info' : 'bg-primary';
                $label = $commission->type === 'percentage' ? 'Percentage' : 'Fixed';

                return '<span class="badge ' . $badgeClass . '">' . $label . '</span>';
            })
            ->addColumn('display_amount', function (Commission $commission) {
                $amount = number_format((float) $commission->amount, 2);

                return $commission->type === 'percentage' ? ($amount . '%') : ('$' . $amount);
            })
            ->addColumn('status_badge', function (Commission $commission) {
                $badgeClass = $commission->status === 'active' ? 'bg-success' : 'bg-secondary';

                return '<span class="badge ' . $badgeClass . '">' . ucfirst($commission->status) . '</span>';
            })
            ->addColumn('action', function (Commission $commission) {
                return '<div class="d-flex gap-1">'
                    . '<button type="button" class="btn btn-sm btn-primary js-edit-commission" data-id="' . $commission->id . '">Edit</button>'
                    . '<button type="button" class="btn btn-sm btn-danger js-delete-commission" data-id="' . $commission->id . '">Delete</button>'
                    . '</div>';
            })
            ->rawColumns(['type_badge', 'status_badge', 'action'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:commissions,name'],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $commission = Commission::query()->create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Commission created successfully.',
            'data' => [
                'id' => $commission->id,
            ],
        ]);
    }

    public function edit(Commission $commission): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => [
                'id' => $commission->id,
                'name' => $commission->name,
                'type' => $commission->type,
                'amount' => (string) $commission->amount,
                'status' => $commission->status,
            ],
        ]);
    }

    public function update(Request $request, Commission $commission): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('commissions', 'name')->ignore($commission->id),
            ],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $commission->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Commission updated successfully.',
        ]);
    }

    public function destroy(Commission $commission): JsonResponse
    {
        $commission->delete();

        return response()->json([
            'status' => true,
            'message' => 'Commission deleted successfully.',
        ]);
    }
}
