<?php

namespace App\Http\Controllers;

use App\Services\ProfitLossService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfitLossController extends Controller
{
    public function index(Request $request, ProfitLossService $service): Response
    {
        $restaurantId = $request->user()->restaurant_id;

        abort_unless($restaurantId, 403, 'Không tìm thấy nhà hàng.');

        $data = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year = (int) ($data['year'] ?? now()->year);
        $month = (int) ($data['month'] ?? now()->month);

        return Inertia::render('reports/ProfitLoss', [
            'report' => $service->buildWithComparison($restaurantId, $year, $month),
            'filters' => ['year' => $year, 'month' => $month],
        ]);
    }
}
