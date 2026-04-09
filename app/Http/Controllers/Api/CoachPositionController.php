<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoachPosition;
use App\Traits\ApiResponse;

class CoachPositionController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            $positions = CoachPosition::query()
                ->select('id', 'name')
                ->where('status', 'active')
                ->orderBy('name')
                ->get();

            return $this->success($positions, 'Coach positions fetched successfully', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }
}
