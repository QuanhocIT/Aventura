<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerBehaviorLog;
use App\Models\CustomerRfmAnalysis;
use App\Models\MarketingCampaign;
use App\Models\CustomerCampaignLog;
use App\Models\Promotion;
use App\Services\CdpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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

        $restaurant = $request->user()->restaurant;
        if ($restaurant && ! app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'rfm_ai_analysis')) {
            abort(403, 'Gói dịch vụ của bạn không hỗ trợ tính năng Phân tích AI Khách hàng RFM. Vui lòng liên hệ bộ phận hỗ trợ để kích hoạt tính năng này.');
        }

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

        $restaurant = $request->user()->restaurant;
        if ($restaurant && ! app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'rfm_ai_analysis')) {
            abort(403, 'Gói dịch vụ của bạn không hỗ trợ tính năng Phân tích AI Khách hàng RFM.');
        }
        
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

        $restaurant = $request->user()->restaurant;
        if ($restaurant && ! app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'rfm_ai_analysis')) {
            return response()->json(['success' => false, 'message' => 'Gói dịch vụ của bạn không hỗ trợ tính năng này.'], 403);
        }
        
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

    /**
     * Create and store a new marketing campaign.
     */
    public function storeCampaign(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage_customers'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'segment' => ['required', 'string', 'max:100'],
            'channel_type' => ['required', 'string', 'in:sms,zalo,discount'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'message_template' => ['required', 'string'],
        ]);

        $restaurantId = $request->user()->restaurant_id;

        // Query customers in the target segment
        $query = Customer::where('restaurant_id', $restaurantId);
        if ($data['segment'] !== 'all') {
            $query->whereHas('rfmAnalysis', function ($q) use ($data) {
                $q->where('rfm_segment', $data['segment']);
            });
        }
        $customers = $query->get();
        $targetCount = $customers->count();

        return DB::transaction(function () use ($data, $restaurantId, $customers, $targetCount, $request) {
            // If channel is discount/voucher, create a real promotion record
            if ($data['channel_type'] === 'discount' && !empty($data['voucher_code'])) {
                // Check if promotion with this code already exists for this restaurant
                $exists = Promotion::where('restaurant_id', $restaurantId)
                    ->where('code', $data['voucher_code'])
                    ->exists();

                if (!$exists) {
                    Promotion::create([
                        'restaurant_id' => $restaurantId,
                        'name' => 'Chiến dịch: ' . $data['name'],
                        'code' => $data['voucher_code'],
                        'type' => 'percent',
                        'value' => 20.00,
                        'min_order_amount' => 0.00,
                        'max_discount_amount' => 50000.00,
                        'start_date' => now(),
                        'end_date' => now()->addDays(30),
                        'is_active' => true,
                        'is_approved' => true,
                        'created_by' => $request->user()->id,
                        'approved_by' => $request->user()->id,
                    ]);
                }
            }

            // Create campaign record
            $campaign = MarketingCampaign::create([
                'restaurant_id' => $restaurantId,
                'name' => $data['name'],
                'segment' => $data['segment'],
                'channel_type' => $data['channel_type'],
                'voucher_code' => $data['voucher_code'],
                'message_template' => $data['message_template'],
                'target_count' => $targetCount,
                'created_by' => $request->user()->id,
            ]);

            // Create log for each customer
            foreach ($customers as $customer) {
                CustomerCampaignLog::create([
                    'campaign_id' => $campaign->id,
                    'customer_id' => $customer->id,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Chiến dịch "' . $campaign->name . '" đã được khởi chạy thành công tới ' . $targetCount . ' khách hàng.',
                'campaign' => $campaign,
            ]);
        });
    }
}
