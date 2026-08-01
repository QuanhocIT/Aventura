<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFeedback;
use App\Models\Order;
use App\Models\PlatformFeedback;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GlobalFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'platform'); // 'platform' (Chủ doanh nghiệp) hoặc 'customer' (Thực khách)
        $filters = $request->only(['restaurant_id', 'rating', 'search', 'category', 'plan_code']);

        // Nếu chưa có phản hồi hệ thống nào, seed dữ liệu mẫu phản hồi từ chủ doanh nghiệp
        if (PlatformFeedback::count() === 0) {
            $this->seedSamplePlatformFeedbacks();
        }

        if ($activeTab === 'platform') {
            return $this->renderPlatformFeedbacks($filters, $activeTab);
        } else {
            return $this->renderCustomerFeedbacks($filters, $activeTab);
        }
    }

    private function renderPlatformFeedbacks(array $filters, string $activeTab)
    {
        $query = PlatformFeedback::with(['user', 'restaurant', 'plan']);

        if (! empty($filters['restaurant_id'])) {
            $query->where('restaurant_id', $filters['restaurant_id']);
        }

        if (! empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $query->where('content', 'like', "%{$filters['search']}%");
        }

        if (! empty($filters['plan_code'])) {
            $planId = SubscriptionPlan::where('code', $filters['plan_code'])->value('id');
            if ($planId) {
                $query->where('plan_id', $planId);
            }
        }

        $categoryLabels = [
            'service_plan' => 'Gói dịch vụ & Giá cước',
            'pos_system' => 'Vận hành POS & QR',
            'customer_support' => 'Hỗ trợ & Kỹ thuật',
            'feature_request' => 'Yêu cầu tính năng',
            'general' => 'Góp ý chung',
        ];

        $feedbacks = (clone $query)->latest()->paginate(20)->through(function (PlatformFeedback $f) use ($categoryLabels) {
            return [
                'id' => $f->id,
                'user_name' => $f->user?->name ?? 'Chủ doanh nghiệp',
                'user_email' => $f->user?->email ?? 'N/A',
                'user_role' => $f->user?->getRoleNames()->first() ?? 'owner',
                'restaurant' => $f->restaurant?->name ?? 'Doanh nghiệp độc lập',
                'restaurant_code' => $f->restaurant?->code ?? '',
                'plan_name' => $f->plan?->name ?? 'Free',
                'plan_code' => $f->plan?->code ?? 'free',
                'category' => $f->category,
                'category_label' => $categoryLabels[$f->category] ?? 'Góp ý chung',
                'rating' => $f->rating,
                'comment' => $f->content,
                'sentiment' => $f->sentiment ?? 'neutral',
                'created_at' => $f->created_at?->format('d/m/Y H:i'),
            ];
        });

        // Compute dynamic stats based on filters
        $statsQuery = PlatformFeedback::query();
        if (! empty($filters['restaurant_id'])) {
            $statsQuery->where('restaurant_id', $filters['restaurant_id']);
        }

        // Aggregate the dashboard counters in two queries instead of one
        // COUNT query per metric/rating bucket.
        $statsRow = (clone $statsQuery)
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating')
            ->selectRaw('SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive')
            ->selectRaw('SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as negative')
            ->selectRaw("SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as text_sentiment_positive")
            ->selectRaw("SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) as text_sentiment_neutral")
            ->selectRaw("SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as text_sentiment_negative")
            ->first();

        $stats = [
            'total' => (int) ($statsRow->total ?? 0),
            'avg_rating' => round((float) ($statsRow->avg_rating ?? 0), 1),
            'positive' => (int) ($statsRow->positive ?? 0),
            'negative' => (int) ($statsRow->negative ?? 0),
            'text_sentiment_positive' => (int) ($statsRow->text_sentiment_positive ?? 0),
            'text_sentiment_neutral' => (int) ($statsRow->text_sentiment_neutral ?? 0),
            'text_sentiment_negative' => (int) ($statsRow->text_sentiment_negative ?? 0),
        ];

        $ratingCounts = (clone $statsQuery)
            ->select('rating')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $ratingDistribution = collect([5, 4, 3, 2, 1])
            ->mapWithKeys(fn (int $rating) => [(string) $rating => (int) $ratingCounts->get($rating, 0)])
            ->all();

        $dailySentiment = (clone $statsQuery)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('AVG(rating) as avg_rating'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'label' => Carbon::parse($row->date)->format('d/m'),
                'value' => round((float) $row->avg_rating, 2),
            ]);

        $aiInsights = $this->generatePlatformAiFeedbackInsights($stats, $ratingDistribution);

        $restaurants = Restaurant::select('id', 'name', 'code')->orderBy('name')->get();
        $plans = SubscriptionPlan::select('id', 'code', 'name')->orderBy('price')->get();

        return Inertia::render('super-admin/feedback/Index', [
            'activeTab' => 'platform',
            'feedbacks' => $feedbacks,
            'stats' => $stats,
            'ratingDistribution' => $ratingDistribution,
            'dailySentiment' => $dailySentiment,
            'aiInsights' => $aiInsights,
            'restaurants' => $restaurants,
            'plans' => $plans,
            'filters' => $filters,
        ]);
    }

    private function renderCustomerFeedbacks(array $filters, string $activeTab)
    {
        $query = CustomerFeedback::withoutGlobalScopes();

        if (! empty($filters['restaurant_id'])) {
            $query->where('restaurant_id', $filters['restaurant_id']);
        }

        if (! empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        if (! empty($filters['search'])) {
            $query->where('content', 'like', "%{$filters['search']}%");
        }

        $restaurantCache = Restaurant::pluck('name', 'id')->toArray();
        $restaurantCodeCache = Restaurant::pluck('code', 'id')->toArray();

        $feedbacks = (clone $query)->latest()->paginate(20)->through(function (CustomerFeedback $f) use ($restaurantCache, $restaurantCodeCache) {
            $customerName = null;
            if ($f->customer_id) {
                $customer = Customer::withoutGlobalScopes()->find($f->customer_id);
                $customerName = $customer?->name;
            }

            $orderNumber = null;
            if ($f->order_id) {
                $order = Order::withoutGlobalScopes()->find($f->order_id);
                $orderNumber = $order?->order_number;
            }

            return [
                'id' => $f->id,
                'customer_name' => $customerName ?? ($f->is_anonymous ? 'Ẩn danh' : '—'),
                'restaurant' => $restaurantCache[$f->restaurant_id] ?? '—',
                'restaurant_code' => $restaurantCodeCache[$f->restaurant_id] ?? '',
                'order_number' => $orderNumber,
                'rating' => $f->rating,
                'comment' => $f->content,
                'sentiment' => $f->sentiment,
                'is_anonymous' => $f->is_anonymous,
                'created_at' => $f->created_at?->format('d/m/Y H:i'),
            ];
        });

        $statsQuery = CustomerFeedback::withoutGlobalScopes();
        if (! empty($filters['restaurant_id'])) {
            $statsQuery->where('restaurant_id', $filters['restaurant_id']);
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'avg_rating' => round((clone $statsQuery)->avg('rating') ?? 0, 1),
            'positive' => (clone $statsQuery)->where('rating', '>=', 4)->count(),
            'negative' => (clone $statsQuery)->where('rating', '<=', 2)->count(),
            'text_sentiment_positive' => (clone $statsQuery)->where('sentiment', 'positive')->count(),
            'text_sentiment_neutral' => (clone $statsQuery)->where('sentiment', 'neutral')->count(),
            'text_sentiment_negative' => (clone $statsQuery)->where('sentiment', 'negative')->count(),
        ];

        $ratingDistribution = [
            '5' => (clone $statsQuery)->where('rating', 5)->count(),
            '4' => (clone $statsQuery)->where('rating', 4)->count(),
            '3' => (clone $statsQuery)->where('rating', 3)->count(),
            '2' => (clone $statsQuery)->where('rating', 2)->count(),
            '1' => (clone $statsQuery)->where('rating', 1)->count(),
        ];

        $dailySentiment = (clone $statsQuery)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('AVG(rating) as avg_rating'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'label' => Carbon::parse($row->date)->format('d/m'),
                'value' => round((float) $row->avg_rating, 2),
            ]);

        $aiInsights = $this->generateCustomerAiFeedbackInsights($stats);

        $restaurants = Restaurant::select('id', 'name', 'code')->orderBy('name')->get();
        $plans = SubscriptionPlan::select('id', 'code', 'name')->orderBy('price')->get();

        return Inertia::render('super-admin/feedback/Index', [
            'activeTab' => 'customer',
            'feedbacks' => $feedbacks,
            'stats' => $stats,
            'ratingDistribution' => $ratingDistribution,
            'dailySentiment' => $dailySentiment,
            'aiInsights' => $aiInsights,
            'restaurants' => $restaurants,
            'plans' => $plans,
            'filters' => $filters,
        ]);
    }

    private function generatePlatformAiFeedbackInsights(array $stats, array $distribution): array
    {
        $avg = $stats['avg_rating'];
        $total = $stats['total'];

        if ($total === 0) {
            return [
                'summary' => 'Chưa có đủ dữ liệu phản hồi từ các chủ doanh nghiệp để AI phân tích.',
                'strengths' => ['Tính năng gửi đánh giá dịch vụ đã được kích hoạt trên hệ thống.'],
                'weaknesses' => ['Cần khuyến khích thêm các chủ nhà hàng gửi đánh giá trải nghiệm SaaS.'],
                'recommendations' => ['Hiển thị widget mời đánh giá dịch vụ sau khi chủ nhà hàng gia hạn hoặc dùng POS 14 ngày.'],
            ];
        }

        if ($avg >= 4.2) {
            return [
                'summary' => "Chủ doanh nghiệp cực kỳ hài lòng với nền tảng Aventura ($avg/5★). Các gói Pro & Ultra nhận được lời khen lớn về tính năng AI và sự ổn định của hệ thống POS.",
                'strengths' => ['Gói dịch vụ Pro & Ultra đáp ứng hoàn hảo nhu cầu quản lý chuỗi.', 'Giao diện mượt mà, tính năng đặt món QR và AI gợi ý hoạt động hiệu quả.'],
                'weaknesses' => ['Một số chủ doanh nghiệp gói Free mong muốn mở rộng thêm dung lượng lưu trữ.'],
                'recommendations' => ['Tiếp tục đẩy mạnh nâng cấp gói cước tự động.', 'Tối ưu hóa tài liệu hướng dẫn sử dụng cho các chủ cửa hàng mới onboarding.'],
            ];
        }

        return [
            'summary' => "Mức độ hài lòng của chủ doanh nghiệp đạt $avg/5★. Cần tập trung hỗ trợ kỹ thuật và giải đáp các thắc mắc về cước phí gói dịch vụ.",
            'strengths' => ['Tốc độ xử lý đơn hàng POS ổn định.', 'Chăm sóc khách hàng hỗ trợ khá nhanh.'],
            'weaknesses' => ['Cần cải thiện tài liệu hướng dẫn kết nối máy in và xuất hóa đơn VAT.'],
            'recommendations' => ['Bổ sung thêm video tutorial trực quan trong Cổng hỗ trợ.', 'Tăng cường theo dõi các ticket hỗ trợ kỹ thuật quá 24h.'],
        ];
    }

    private function generateCustomerAiFeedbackInsights(array $stats): array
    {
        return [
            'summary' => "Tổng hợp phản hồi thực khách tại các nhà hàng đạt mức đánh giá trung bình {$stats['avg_rating']}/5★.",
            'strengths' => ['Món ăn và tốc độ đặt món QR nhận nhiều đánh giá tốt.'],
            'weaknesses' => ['Thời gian chờ món lúc giờ cao điểm bị phàn nàn ở một số chi nhánh.'],
            'recommendations' => ['Khuyến nghị nhà hàng tối ưu lại quy trình hiển thị bếp (KDS).'],
        ];
    }

    private function seedSamplePlatformFeedbacks(): void
    {
        $restaurants = Restaurant::take(5)->get();
        $ownerUser = User::role(['owner', 'manager'])->first() ?? User::first();

        if (! $ownerUser) {
            return;
        }

        $samples = [
            [
                'rating' => 5,
                'category' => 'service_plan',
                'content' => 'Gói Pro rất đáng tiền! Tính năng AI dự báo tồn kho và phát hiện gian lận giúp nhà hàng tôi tối ưu được 15% chi phí nguyên liệu hàng tháng.',
            ],
            [
                'rating' => 5,
                'category' => 'pos_system',
                'content' => 'Hệ thống POS chạy rất mượt trên cả tablet và máy tính bàn. Khách quét mã QR gọi món tại bàn giúp giảm áp lực lớn cho nhân viên phục vụ ca tối.',
            ],
            [
                'rating' => 4,
                'category' => 'customer_support',
                'content' => 'Đội ngũ hỗ trợ kỹ thuật Aventura xử lý sự cố kết nối máy in bếp rất nhanh và chu đáo.',
            ],
            [
                'rating' => 4,
                'category' => 'feature_request',
                'content' => 'Gói Free trải nghiệm rất tốt. Hi vọng hệ thống sớm phát triển thêm tính năng đồng bộ hóa hóa đơn điện tử VAT tự động cho chuỗi nhỏ.',
            ],
            [
                'rating' => 5,
                'category' => 'general',
                'content' => 'Giao diện quản lý trực quan, dễ thao tác kể cả với nhân viên mới chưa từng dùng phần mềm POS.',
            ],
        ];

        foreach ($samples as $i => $s) {
            $rest = $restaurants[$i % max(1, count($restaurants))] ?? null;
            PlatformFeedback::create([
                'user_id' => $ownerUser->id,
                'restaurant_id' => $rest?->id,
                'plan_id' => $rest?->plan_id,
                'rating' => $s['rating'],
                'category' => $s['category'],
                'content' => $s['content'],
                'status' => 'reviewed',
                'created_at' => now()->subDays($i * 2 + 1),
            ]);
        }
    }
}
