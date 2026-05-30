<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MenuInsightService
{
    /**
     * Phân tích hiệu suất sản phẩm và trả về danh sách insights có thể hành động.
     */
    public function getInsights(int $restaurantId, int $days = 30): array
    {
        $products = $this->queryProductPerformance($restaurantId, $days);

        if ($products->isEmpty()) {
            return [];
        }

        $maxRevenue = (float) $products->max('total_revenue');
        $maxQty     = (float) $products->max('total_qty');
        $insights   = [];

        foreach ($products as $p) {
            $revenue  = (float) $p->total_revenue;
            $qty      = (int)   $p->total_qty;
            $price    = (float) $p->price;
            $cost     = (float) ($p->cost_price ?? 0);
            $margin   = $price > 0 && $cost > 0
                ? round((($price - $cost) / $price) * 100, 1)
                : null;

            // ── Cảnh báo biên lợi nhuận thấp ─────────────────────────────────
            if ($margin !== null && $margin < 25 && $qty > 0) {
                $insights[] = [
                    'type'       => 'low_margin',
                    'severity'   => $margin < 10 ? 'critical' : 'warning',
                    'product'    => $p->name,
                    'product_id' => $p->product_id,
                    'message'    => "Biên lợi nhuận <strong>{$p->name}</strong> chỉ <strong>{$margin}%</strong> — dưới ngưỡng an toàn 25%",
                    'suggestion' => 'Tăng giá bán hoặc tối ưu chi phí nguyên liệu',
                    'value'      => $margin,
                    'unit'       => '%',
                ];
            }

            // ── Sản phẩm bán chậm ────────────────────────────────────────────
            $weeklyAvg = round($qty / ($days / 7), 1);
            if ($weeklyAvg < 3 && $qty > 0) {
                $insights[] = [
                    'type'       => 'slow_moving',
                    'severity'   => 'info',
                    'product'    => $p->name,
                    'product_id' => $p->product_id,
                    'message'    => "<strong>{$p->name}</strong> chỉ bán <strong>{$weeklyAvg} lần/tuần</strong> trong {$days} ngày qua",
                    'suggestion' => 'Cân nhắc combo kích cầu hoặc ẩn khỏi menu nếu không sinh lời',
                    'value'      => $weeklyAvg,
                    'unit'       => 'lần/tuần',
                ];
            }

            // ── Sản phẩm bán chạy nhưng biên lợi thấp (star với vấn đề) ──────
            if ($margin !== null && $revenue > $maxRevenue * 0.3 && $margin < 30) {
                $insights[] = [
                    'type'       => 'high_volume_low_margin',
                    'severity'   => 'warning',
                    'product'    => $p->name,
                    'product_id' => $p->product_id,
                    'message'    => "<strong>{$p->name}</strong> bán chạy nhưng margin thấp ({$margin}%)",
                    'suggestion' => 'Đây là cơ hội lớn: tối ưu chi phí để tăng lợi nhuận đáng kể',
                    'value'      => $margin,
                    'unit'       => '%',
                ];
            }
        }

        // Sắp xếp: critical trước, warning sau, info cuối
        usort($insights, fn ($a, $b) => [
            'critical' => 0, 'warning' => 1, 'info' => 2,
        ][$a['severity']] <=> [
            'critical' => 0, 'warning' => 1, 'info' => 2,
        ][$b['severity']]);

        return array_slice($insights, 0, 8); // Tối đa 8 insights
    }

    /**
     * Dữ liệu BCG matrix: xếp sản phẩm vào 4 ô.
     */
    public function getBcgData(int $restaurantId, int $days = 30): array
    {
        $products = $this->queryProductPerformance($restaurantId, $days);

        if ($products->isEmpty()) return [];

        $medianRevenue = $this->median($products->pluck('total_revenue')->map(fn ($v) => (float) $v)->all());
        $medianQty     = $this->median($products->pluck('total_qty')->map(fn ($v) => (int) $v)->all());

        return $products->map(fn ($p) => [
            'name'          => $p->name,
            'product_id'    => $p->product_id,
            'total_revenue' => (float) $p->total_revenue,
            'total_qty'     => (int)   $p->total_qty,
            'quadrant'      => $this->classifyBcg(
                (float) $p->total_revenue,
                (int)   $p->total_qty,
                $medianRevenue,
                $medianQty
            ),
        ])->values()->all();
    }

    /**
     * Hiệu suất sản phẩm + biên lợi nhuận cho chart.
     */
    public function getProductMargins(int $restaurantId, int $days = 30): array
    {
        $products = $this->queryProductPerformance($restaurantId, $days);

        return $products
            ->filter(fn ($p) => (int) $p->total_qty > 0)
            ->map(fn ($p) => [
                'name'       => $p->name,
                'margin'     => (float) $p->price > 0 && (float) $p->cost_price > 0
                    ? round(((float) $p->price - (float) $p->cost_price) / (float) $p->price * 100, 1)
                    : null,
                'revenue'    => (float) $p->total_revenue,
                'qty'        => (int)   $p->total_qty,
            ])
            ->filter(fn ($p) => $p['margin'] !== null)
            ->sortByDesc('revenue')
            ->take(10)
            ->values()
            ->all();
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function queryProductPerformance(int $restaurantId, int $days)
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'completed')
            ->where('orders.completed_at', '>=', now()->subDays($days))
            ->select(
                'products.id as product_id',
                'products.name',
                'products.price',
                'products.cost_price',
                DB::raw('SUM(order_items.quantity) as total_qty'),
                DB::raw('SUM(order_items.line_total) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.price', 'products.cost_price')
            ->orderByDesc('total_revenue')
            ->get();
    }

    private function classifyBcg(float $revenue, int $qty, float $medianRevenue, float $medianQty): string
    {
        $highRevenue = $revenue >= $medianRevenue;
        $highQty     = $qty >= $medianQty;

        return match (true) {
            $highRevenue && $highQty     => 'star',       // ⭐ Bán nhiều, doanh thu cao
            $highRevenue && !$highQty    => 'cash_cow',   // 🐄 Ít bán nhưng doanh thu cao (đắt tiền)
            !$highRevenue && $highQty    => 'question',   // ❓ Bán nhiều nhưng rẻ
            default                      => 'dog',        // 🐶 Bán ít, doanh thu thấp
        };
    }

    private function median(array $values): float
    {
        if (empty($values)) return 0;
        sort($values);
        $count = count($values);
        $mid   = (int) floor($count / 2);
        return $count % 2 === 0
            ? ($values[$mid - 1] + $values[$mid]) / 2
            : $values[$mid];
    }
}
