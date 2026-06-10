<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerBehaviorLog;
use App\Models\CustomerRfmAnalysis;
use App\Services\CdpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CdpController extends Controller
{
    /**
     * Display the CDP & RFM Dashboard.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('manage_customers'), 403, 'Bạn không có quyền truy cập CDP & Phân tích RFM.');

        $restaurantId = $request->user()->restaurant_id;

        // Perform recalculation on-demand if there are any uncalculated customer records
        $metrics = CdpService::getRfmMetrics($restaurantId);

        $customers = Customer::where('restaurant_id', $restaurantId)
            ->with('rfmAnalysis')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'customer_code' => 'KH-' . str_pad($c->id, 5, '0', STR_PAD_LEFT),
                'full_name' => $c->full_name,
                'phone' => $c->phone,
                'email' => $c->email,
                'loyalty_points' => $c->loyalty_points,
                'last_order_at' => $c->last_order_at ? $c->last_order_at->format('H:i d/m/Y') : 'Chưa có',
                'rfm_segment' => $c->rfmAnalysis?->rfm_segment ?? 'New',
                'rfm_score_code' => $c->rfmAnalysis?->rfm_score_code ?? '111',
                'monetary_amount' => (float) ($c->rfmAnalysis?->monetary_amount ?? 0.0),
                'frequency_count' => (int) ($c->rfmAnalysis?->frequency_count ?? 0),
                'recency_days' => (int) ($c->rfmAnalysis?->recency_days ?? 999),
            ]);

        return Inertia::render('customers/CdpDashboard', [
            'metrics' => $metrics,
            'customers' => $customers,
        ]);
    }

    /**
     * Force recalculate RFM scores for all customers.
     */
    public function recalculate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage_customers'), 403);
        
        $restaurantId = $request->user()->restaurant_id;
        CdpService::calculateRfmForRestaurant($restaurantId);

        return back()->with('success', 'Đã làm mới dữ liệu chấm điểm & phân cụm RFM thành công.');
    }

    /**
     * API to fetch customers belonging to a specific segment.
     */
    public function segment(Request $request, string $segment): JsonResponse
    {
        abort_unless($request->user()->can('manage_customers'), 403);
        
        $restaurantId = $request->user()->restaurant_id;

        $customers = Customer::where('customers.restaurant_id', $restaurantId)
            ->join('customer_rfm_analysis', 'customers.id', '=', 'customer_rfm_analysis.customer_id')
            ->where('customer_rfm_analysis.rfm_segment', $segment)
            ->select('customers.*', 'customer_rfm_analysis.rfm_score_code', 'customer_rfm_analysis.monetary_amount', 'customer_rfm_analysis.frequency_count', 'customer_rfm_analysis.recency_days')
            ->latest()
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'customer_code' => 'KH-' . str_pad($c->id, 5, '0', STR_PAD_LEFT),
                'full_name' => $c->full_name,
                'phone' => $c->phone,
                'email' => $c->email,
                'loyalty_points' => $c->loyalty_points,
                'rfm_segment' => $segment,
                'rfm_score_code' => $c->rfm_score_code,
                'monetary_amount' => (float) $c->monetary_amount,
                'frequency_count' => (int) $c->frequency_count,
                'recency_days' => (int) $c->recency_days,
            ]);

        return response()->json([
            'success' => true,
            'segment' => $segment,
            'customers' => $customers,
        ]);
    }

    /**
     * public/guest tracking endpoint.
     */
    public function trackBehavior(Request $request): JsonResponse
    {
        $data = $request->validate([
            'restaurant_id' => ['required', 'integer'],
            'session_id' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:100'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'numeric'],
            'meta_data' => ['nullable', 'array'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $customerId = null;
        if (!empty($data['customer_phone'])) {
            $customer = Customer::where('restaurant_id', $data['restaurant_id'])
                ->where('phone', $data['customer_phone'])
                ->first();
            if ($customer) {
                $customerId = $customer->id;
            }
        }

        CdpService::logBehavior(
            (int) $data['restaurant_id'],
            $data['session_id'],
            $data['event_type'],
            !empty($data['product_id']) ? (int) $data['product_id'] : null,
            !empty($data['quantity']) ? (float) $data['quantity'] : null,
            $data['meta_data'] ?? null,
            $customerId
        );

        return response()->json([
            'success' => true,
            'message' => 'Hành vi đã được ghi nhận vào hệ thống CDP.',
        ]);
    }
}
