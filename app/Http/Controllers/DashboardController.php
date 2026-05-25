<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\Order;
use App\Models\RestaurantRevenueSummary;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // Bài nổi bật (hero)
        $featuredRaw = NewsPost::published()
            ->where('is_featured', true)
            ->latest('published_at')
            ->first();

        $featuredPost = $featuredRaw ? [
            'id'                 => $featuredRaw->id,
            'title'              => $featuredRaw->title,
            'slug'               => $featuredRaw->slug,
            'excerpt'            => $featuredRaw->excerpt,
            'category'           => $featuredRaw->category,
            'featured_image_url' => $featuredRaw->featured_image_url,
            'is_featured'        => true,
            'published_at'       => $featuredRaw->published_at?->format('d/m/Y'),
        ] : null;

        // Các bài còn lại (loại trừ bài featured nếu có)
        $latestNews = NewsPost::published()
            ->when($featuredRaw, fn ($q) => $q->where('id', '!=', $featuredRaw->id))
            ->latest('published_at')
            ->take(4)
            ->get()
            ->map(fn (NewsPost $p) => [
                'id'                 => $p->id,
                'title'              => $p->title,
                'slug'               => $p->slug,
                'excerpt'            => $p->excerpt,
                'category'           => $p->category,
                'featured_image_url' => $p->featured_image_url,
                'is_featured'        => $p->is_featured,
                'published_at'       => $p->published_at?->format('d/m/Y'),
            ])->all();

        $user       = auth()->user();
        $restaurant = $user?->restaurant;

        $stats        = null;
        $recentOrders = [];
        $alerts       = [];

        if ($restaurant) {
            $todaySummary = RestaurantRevenueSummary::where('restaurant_id', $restaurant->id)
                ->whereDate('summary_date', today())
                ->first();

            $ordersToday    = Order::where('restaurant_id', $restaurant->id)->whereDate('created_at', today());
            $totalToday     = (clone $ordersToday)->count();
            $completedToday = (clone $ordersToday)->where('status', 'completed')->count();
            $cancelledToday = (clone $ordersToday)->where('status', 'cancelled')->count();

            $stats = [
                'products_count'    => $restaurant->products()->count(),
                'employees_count'   => $restaurant->employees()->where('status', 'active')->count(),
                'branches_count'    => $restaurant->branches()->count(),
                'tables_count'      => $restaurant->tables()->count(),
                'orders_today'      => $totalToday,
                'revenue_today'     => $todaySummary?->total_revenue ?? 0,
                'orders_completed'  => $completedToday,
                'orders_cancelled'  => $cancelledToday,
            ];

            // ── Đơn hàng gần đây (5 đơn mới nhất) ─────────────
            $recentOrders = Order::with('table')
                ->where('restaurant_id', $restaurant->id)
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (Order $o) => [
                    'id'           => $o->id,
                    'order_number' => $o->order_number,
                    'table_name'   => $o->table?->name ?? null,
                    'total_amount' => (float) $o->total_amount,
                    'status'       => $o->status,
                    'payment_status' => $o->payment_status,
                    'channel'      => $o->channel,
                    'created_at'   => $o->created_at?->format('H:i'),
                ])
                ->all();

            // ── Cảnh báo & Nhắc nhở ─────────────────────────────
            // 1. Đơn pending lâu hơn 30 phút
            $stuckPending = Order::where('restaurant_id', $restaurant->id)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(30))
                ->count();
            if ($stuckPending > 0) {
                $alerts[] = [
                    'type'    => 'warning',
                    'message' => "{$stuckPending} đơn hàng đang chờ xử lý quá 30 phút",
                    'href'    => '/orders?status=pending',
                ];
            }

            // 2. Tỉ lệ huỷ cao hôm nay (> 20%)
            if ($totalToday > 0 && ($cancelledToday / $totalToday) > 0.2) {
                $pct = round(($cancelledToday / $totalToday) * 100);
                $alerts[] = [
                    'type'    => 'danger',
                    'message' => "Tỉ lệ huỷ đơn hôm nay cao: {$pct}% ({$cancelledToday}/{$totalToday} đơn)",
                    'href'    => '/orders?status=cancelled',
                ];
            }

            // 3. Đơn confirmed/preparing không chuyển sang completed (> 1 giờ)
            $stuckProcessing = Order::where('restaurant_id', $restaurant->id)
                ->whereIn('status', ['confirmed', 'preparing'])
                ->where('updated_at', '<', now()->subHour())
                ->count();
            if ($stuckProcessing > 0) {
                $alerts[] = [
                    'type'    => 'info',
                    'message' => "{$stuckProcessing} đơn đang chế biến chưa được cập nhật trạng thái",
                    'href'    => '/orders',
                ];
            }
        }

        $onboardingStatus   = $user?->onboarding_status ?? [];
        $onboardingComplete = !empty($onboardingStatus['day_1']['completed_at'])
            && !empty($onboardingStatus['day_2']['completed_at'])
            && !empty($onboardingStatus['day_3']['completed_at']);

        return Inertia::render('Dashboard', [
            'latestNews'         => $latestNews,
            'featuredPost'       => $featuredPost,
            'stats'              => $stats,
            'onboardingComplete' => $onboardingComplete,
            'recentOrders'       => $recentOrders,
            'alerts'             => $alerts,
        ]);
    }
}
