<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerPosition;
use App\Traits\ApiResponse;

class PlayerPositionController extends Controller
{
    use ApiResponse;
    public function index()
    {
        try {
            $positions = PlayerPosition::query()
                ->select('id', 'name')
                ->where('status', 'active')
                ->orderBy('name')
                ->get();

            return $this->success($positions, 'Player positions fetched successfully', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }
}
