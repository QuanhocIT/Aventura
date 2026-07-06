<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerBehaviorLog;
use App\Models\CustomerRfmAnalysis;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CdpService
{
    /**
     * Log a digital footprint behavior.
     */
    public static function logBehavior(
        int $restaurantId,
        string $sessionId,
        string $eventType,
        ?int $productId = null,
        ?float $quantity = null,
        ?array $metaData = null,
        ?int $customerId = null
    ): void {
        CustomerBehaviorLog::create([
            'restaurant_id' => $restaurantId,
            'session_id' => $sessionId,
            'customer_id' => $customerId,
            'event_type' => $eventType,
            'product_id' => $productId,
            'quantity' => $quantity,
            'meta_data' => $metaData,
        ]);

        // If customer is identified, backfill any historical logs for this session
        if ($customerId) {
            CustomerBehaviorLog::where('session_id', $sessionId)
                ->whereNull('customer_id')
                ->update(['customer_id' => $customerId]);
        }
    }

    /**
     * Calculate and cache RFM score for a specific customer.
     */
    public static function calculateRfmForCustomer(Customer $customer): void
    {
        $restaurantId = $customer->restaurant_id;

        // Fetch completed orders
        $orders = Order::where('customer_id', $customer->id)
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->get();

        $frequencyCount = $orders->count();
        $monetaryAmount = (float) $orders->sum('total_amount');

        // Update total spent and recalculate tier via LoyaltyService
        $customer->update(['total_spent' => $monetaryAmount]);
        app(\App\Services\LoyaltyService::class)->recalculateTier($customer);

        // Recency calculation
        $lastOrder = $orders->sortByDesc('completed_at')->first();
        $recencyDays = 999;
        if ($lastOrder && $lastOrder->completed_at) {
            $recencyDays = (int) Carbon::parse($lastOrder->completed_at)->diffInDays(now());
        }

        // RFM Scoring Thresholds
        // Recency score (higher is more recent)
        if ($recencyDays <= 3) {
            $recencyScore = 5;
        } elseif ($recencyDays <= 10) {
            $recencyScore = 4;
        } elseif ($recencyDays <= 30) {
            $recencyScore = 3;
        } elseif ($recencyDays <= 90) {
            $recencyScore = 2;
        } else {
            $recencyScore = 1;
        }

        // Frequency score (higher is more frequent)
        if ($frequencyCount >= 10) {
            $frequencyScore = 5;
        } elseif ($frequencyCount >= 5) {
            $frequencyScore = 4;
        } elseif ($frequencyCount >= 3) {
            $frequencyScore = 3;
        } elseif ($frequencyCount >= 2) {
            $frequencyScore = 2;
        } else {
            $frequencyScore = 1;
        }

        // Monetary score (higher is more spend)
        if ($monetaryAmount >= 2000000) {
            $monetaryScore = 5;
        } elseif ($monetaryAmount >= 1000000) {
            $monetaryScore = 4;
        } elseif ($monetaryAmount >= 500000) {
            $monetaryScore = 3;
        } elseif ($monetaryAmount >= 200000) {
            $monetaryScore = 2;
        } else {
            $monetaryScore = 1;
        }

        // Check if customer is a Sales Hunter (Săn sale)
        // Ratio of discounts to subtotal is high (> 15%)
        $totalSubtotal = (float) $orders->sum('subtotal');
        $totalDiscounts = (float) $orders->sum('discount_amount');
        $discountRatio = $totalSubtotal > 0 ? ($totalDiscounts / $totalSubtotal) : 0.0;

        $isSalesHunter = $discountRatio > 0.15 || ($monetaryScore <= 2 && $frequencyScore >= 3);

        // Map scores to standard RFM segments
        $rfmScoreCode = "{$recencyScore}{$frequencyScore}{$monetaryScore}";

        if ($recencyScore >= 4 && $frequencyScore >= 4 && $monetaryScore >= 4) {
            $segment = 'VIP'; // Champions / VIP
        } elseif ($recencyScore >= 3 && $frequencyScore >= 3 && $monetaryScore >= 3) {
            $segment = 'Loyal'; // Loyal Members (Thành viên trung thành)
        } elseif ($recencyScore >= 4 && $frequencyScore <= 1) {
            $segment = 'New'; // New Customers (Khách mới)
        } elseif ($isSalesHunter) {
            $segment = 'SalesHunter'; // Sales Hunters (Khách săn sale)
        } elseif ($recencyScore == 2 && $frequencyScore >= 2) {
            $segment = 'AboutToLeave'; // About to Leave (Sắp rời bỏ)
        } elseif ($recencyScore == 1) {
            $segment = 'Lost'; // At Risk / Lost (Đã rời bỏ)
        } else {
            $segment = 'Potential'; // Potential / Promising (Khách tiềm năng)
        }

        // Cache RFM scores
        CustomerRfmAnalysis::updateOrCreate(
            [
                'restaurant_id' => $restaurantId,
                'customer_id' => $customer->id,
            ],
            [
                'recency_days' => $recencyDays,
                'frequency_count' => $frequencyCount,
                'monetary_amount' => $monetaryAmount,
                'recency_score' => $recencyScore,
                'frequency_score' => $frequencyScore,
                'monetary_score' => $monetaryScore,
                'rfm_score_code' => $rfmScoreCode,
                'rfm_segment' => $segment,
                'last_calculated_at' => now(),
            ]
        );
    }

    /**
     * Calculate RFM scores for all customers in a restaurant.
     */
    public static function calculateRfmForRestaurant(int $restaurantId): void
    {
        Customer::where('restaurant_id', $restaurantId)->chunk(100, function ($customers) {
            foreach ($customers as $customer) {
                self::calculateRfmForCustomer($customer);
            }
        });
    }

    /**
     * Get RFM dashboard metrics.
     */
    public static function getRfmMetrics(int $restaurantId): array
    {
        // First ensure all customers have their RFM records
        $uncalculatedCount = Customer::where('restaurant_id', $restaurantId)
            ->whereNotExists(function ($query) use ($restaurantId) {
                $query->select(DB::raw(1))
                    ->from('customer_rfm_analysis')
                    ->whereColumn('customer_rfm_analysis.customer_id', 'customers.id')
                    ->where('customer_rfm_analysis.restaurant_id', $restaurantId);
            })->count();

        if ($uncalculatedCount > 0) {
            self::calculateRfmForRestaurant($restaurantId);
        }

        // Load segments counts
        $segments = CustomerRfmAnalysis::where('restaurant_id', $restaurantId)
            ->select('rfm_segment', DB::raw('COUNT(*) as count'), DB::raw('SUM(monetary_amount) as total_revenue'))
            ->groupBy('rfm_segment')
            ->get()
            ->keyBy('rfm_segment');

        $availableSegments = ['VIP', 'Loyal', 'New', 'SalesHunter', 'AboutToLeave', 'Lost', 'Potential'];
        $segmentStats = [];
        $totalCustomers = 0;
        $totalRevenue = 0.0;

        foreach ($availableSegments as $seg) {
            $data = $segments->get($seg);
            $count = $data ? (int) $data->count : 0;
            $revenue = $data ? (float) $data->total_revenue : 0.0;
            
            $segmentStats[$seg] = [
                'count' => $count,
                'revenue' => $revenue,
                'avg_spend' => $count > 0 ? round($revenue / $count, 2) : 0.0,
            ];
            $totalCustomers += $count;
            $totalRevenue += $revenue;
        }

        // Get average order frequency and customer CLV
        $avgCLV = $totalCustomers > 0 ? round($totalRevenue / $totalCustomers, 2) : 0.0;

        $avgFrequency = (float) CustomerRfmAnalysis::where('restaurant_id', $restaurantId)
            ->avg('frequency_count') ?? 0.0;

        // Recent behaviors
        $recentLogs = CustomerBehaviorLog::where('customer_behavior_logs.restaurant_id', $restaurantId)
            ->leftJoin('customers', 'customer_behavior_logs.customer_id', '=', 'customers.id')
            ->leftJoin('products', 'customer_behavior_logs.product_id', '=', 'products.id')
            ->select(
                'customer_behavior_logs.*',
                'customers.full_name as customer_name',
                'customers.phone as customer_phone',
                'products.name as product_name'
            )
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'session_id' => $log->session_id,
                'customer_name' => $log->customer_name ?: 'Khách ẩn danh',
                'customer_phone' => $log->customer_phone,
                'event_type' => $log->event_type,
                'product_name' => $log->product_name,
                'quantity' => (float) $log->quantity,
                'meta_data' => $log->meta_data,
                'created_at' => $log->created_at->format('H:i d/m/Y'),
            ]);

        return [
            'total_customers' => $totalCustomers,
            'total_revenue' => $totalRevenue,
            'avg_clv' => $avgCLV,
            'avg_frequency' => round($avgFrequency, 1),
            'segments' => $segmentStats,
            'recent_logs' => $recentLogs,
        ];
    }
}
