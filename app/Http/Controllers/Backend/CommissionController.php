<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CommissionController extends Controller
{
    public function index(): View
    {
        $targetUsers = User::query()
            ->whereIn('role', ['coach', 'club'])
            ->orderBy('name')
            ->get(['id', 'name', 'last_name', 'email', 'role'])
            ->map(function (User $user): array {
                return [
                    'id' => $user->id,
                    'label' => trim((string) $user->name . ' ' . (string) $user->last_name) . ' (' . $user->email . ')',
                    'role' => strtolower((string) $user->role),
                ];
            })
            ->values();

        return view('Backend.settings.commissions', [
            'targetUsers' => $targetUsers,
        ]);
    }

    public function data(): JsonResponse
    {
        $commissions = Commission::query()
            ->with('user:id,name,last_name,email,role')
            ->latest();

        return DataTables::eloquent($commissions)
            ->addColumn('applies_to_badge', function (Commission $commission) {
                $target = strtolower((string) ($commission->applies_to ?: 'all'));
                $badgeClass = match ($target) {
                    'coach' => 'bg-primary',
                    'club' => 'bg-warning text-dark',
                    default => 'bg-dark',
                };

                return '<span class="badge ' . $badgeClass . '">' . ucfirst($target) . '</span>';
            })
            ->addColumn('type_badge', function (Commission $commission) {
                $badgeClass = $commission->type === 'percentage' ? 'bg-info' : 'bg-primary';
                $label = $commission->type === 'percentage' ? 'Percentage' : 'Fixed';

                return '<span class="badge ' . $badgeClass . '">' . $label . '</span>';
            })
            ->addColumn('target_user', function (Commission $commission) {
                if (! $commission->user) {
                    return '<span class="text-muted">All ' . ucfirst((string) $commission->applies_to) . '</span>';
                }

                $name = trim((string) $commission->user->name . ' ' . (string) $commission->user->last_name);
                $role = ucfirst((string) $commission->user->role);

                return e($name !== '' ? $name : (string) $commission->user->email) . ' <small class="text-muted">(' . e($role) . ')</small>';
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
                    . '<button type="button" class="btn btn-sm btn-icon btn-primary js-edit-commission" data-id="' . $commission->id . '" title="Edit"><i class="bi bi-pencil-square text-white"></i></button>'
                    . '<button type="button" class="btn btn-sm btn-icon btn-danger js-delete-commission" data-id="' . $commission->id . '" title="Delete"><i class="bi bi-trash text-white"></i></button>'
                    . '</div>';
            })
            ->rawColumns(['applies_to_badge', 'type_badge', 'target_user', 'status_badge', 'action'])
            ->toJson();
    }

    private function normalizeTargetUser(array $validated): array
    {
        if (($validated['applies_to'] ?? 'all') === 'all') {
            $validated['user_id'] = null;

            return $validated;
        }

        $targetUserId = $validated['user_id'] ?? null;
        if (empty($targetUserId)) {
            $validated['user_id'] = null;

            return $validated;
        }

        $targetUser = User::query()->find((int) $targetUserId);
        if (! $targetUser || strtolower((string) $targetUser->role) !== strtolower((string) $validated['applies_to'])) {
            throw ValidationException::withMessages([
                'user_id' => ['Selected user does not match Applies To role.'],
            ]);
        }

        $validated['user_id'] = $targetUser->id;

        return $validated;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:commissions,name'],
            'applies_to' => ['required', Rule::in(['all', 'coach', 'club'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $validated = $this->normalizeTargetUser($validated);

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
                'applies_to' => $commission->applies_to,
                'user_id' => $commission->user_id,
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
            'applies_to' => ['required', Rule::in(['all', 'coach', 'club'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $validated = $this->normalizeTargetUser($validated);

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
