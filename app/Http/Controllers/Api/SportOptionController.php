<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SportOption;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SportOptionController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        try {
            $audience = strtolower(trim((string) $request->query('audience', '')));

            $query = SportOption::query()
                ->select('id', 'name', 'audience', 'status')
                ->where('status', 'active');

            if ($audience !== '') {
                $query->where('audience', $audience);
            }

            $options = $query
                ->orderBy('name')
                ->get();

            return $this->success($options, 'Sport options fetched successfully', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }
}
