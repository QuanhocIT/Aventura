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

        $mapped = $products->map(function ($p) {
            $price = (float) $p->price;
            $cost = (float) ($p->cost_price ?? 0);
            $margin = $price > 0 ? (($price - $cost) / $price) * 100 : 0.0;
            return [
                'name'          => $p->name,
                'product_id'    => $p->product_id,
                'total_revenue' => (float) $p->total_revenue,
                'total_qty'     => (int)   $p->total_qty,
                'price'         => $price,
                'cost_price'    => $cost,
                'margin'        => round($margin, 1),
            ];
        });

        $medianQty = $this->median($mapped->pluck('total_qty')->all());
        $medianMargin = $this->median($mapped->pluck('margin')->all());

        return $mapped->map(function ($item) use ($medianQty, $medianMargin) {
            $highQty = $item['total_qty'] >= $medianQty;
            $highMargin = $item['margin'] >= $medianMargin;

            $quadrant = match (true) {
                $highQty && $highMargin     => 'star',       // ⭐ Stars
                $highQty && !$highMargin    => 'plowhorse',  // 🐎 Plowhorses
                !$highQty && $highMargin    => 'puzzle',     // 🧩 Puzzles
                default                     => 'dog',        // 🐶 Dogs
            };

            // AI suggestions for action
            $recommendation = match ($quadrant) {
                'star' => 'Món ăn Ngôi sao: Sản lượng cao & Lợi nhuận cao. Hãy duy trì vị trí nổi bật trên thực đơn, giữ nguyên giá bán và chất lượng để bảo vệ dòng tiền.',
                'plowhorse' => 'Món ăn Bò sữa: Bán rất tốt nhưng lợi nhuận thấp. Hãy thử đàm phán giảm giá nguyên vật liệu, hoặc tăng giá bán nhẹ (khoảng 3-5%), hoặc giảm kích thước khẩu phần một chút.',
                'puzzle' => 'Món ăn Câu đố: Biên lợi nhuận cực tốt nhưng kén khách. Đề xuất ghép món này vào các COMBO bán kèm cùng món bán chạy, tăng cường PR, hoặc đưa lên vị trí bắt mắt nhất trên thực đơn.',
                'dog' => 'Món ăn Thú cưng: Cả sản lượng và biên lợi nhuận đều thấp. Cân nhắc loại bỏ hoàn toàn khỏi thực đơn, hoặc thay thế bằng một công thức mới thu hút khách hơn.',
            };

            return array_merge($item, [
                'quadrant' => $quadrant,
                'ai_recommendation' => $recommendation,
                'median_qty' => $medianQty,
                'median_margin' => $medianMargin,
            ]);
        })->values()->all();
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
}
