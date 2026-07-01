<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFeedback;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class GlobalFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['restaurant_id', 'rating', 'search']);

        $query = CustomerFeedback::withoutGlobalScopes();

        if (!empty($filters['restaurant_id'])) {
            $query->where('restaurant_id', $filters['restaurant_id']);
        }

        if (!empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        if (!empty($filters['search'])) {
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
                'comment' => $f->content, // Map content to comment for frontend compatibility
                'is_anonymous' => $f->is_anonymous,
                'created_at' => $f->created_at?->format('d/m/Y H:i'),
            ];
        });

        // Compute dynamic stats based on filters
        $statsQuery = CustomerFeedback::withoutGlobalScopes();
        if (!empty($filters['restaurant_id'])) {
            $statsQuery->where('restaurant_id', $filters['restaurant_id']);
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'avg_rating' => round((clone $statsQuery)->avg('rating') ?? 0, 1),
            'positive' => (clone $statsQuery)->where('rating', '>=', 4)->count(),
            'negative' => (clone $statsQuery)->where('rating', '<=', 2)->count(),
        ];

        // 1. Rating Distribution (percentage of stars 1-5)
        $ratingDistribution = [
            '5' => (clone $statsQuery)->where('rating', 5)->count(),
            '4' => (clone $statsQuery)->where('rating', 4)->count(),
            '3' => (clone $statsQuery)->where('rating', 3)->count(),
            '2' => (clone $statsQuery)->where('rating', 2)->count(),
            '1' => (clone $statsQuery)->where('rating', 1)->count(),
        ];

        // 2. Daily Sentiment (Rating average over time)
        $dailySentiment = (clone $statsQuery)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('AVG(rating) as avg_rating'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'label' => Carbon::parse($row->date)->format('d/m'),
                'value' => round((float) $row->avg_rating, 2),
            ]);

        // 3. AI Insights & Suggestions generator
        $aiInsights = $this->generateAiFeedbackInsights($stats, $ratingDistribution);

        $restaurants = Restaurant::select('id', 'name', 'code')->orderBy('name')->get();

        return Inertia::render('super-admin/feedback/Index', [
            'feedbacks' => $feedbacks,
            'stats' => $stats,
            'ratingDistribution' => $ratingDistribution,
            'dailySentiment' => $dailySentiment,
            'aiInsights' => $aiInsights,
            'restaurants' => $restaurants,
            'filters' => $filters,
        ]);
    }

    private function generateAiFeedbackInsights(array $stats, array $distribution): array
    {
        $avg = $stats['avg_rating'];
        $total = $stats['total'];

        if ($total === 0) {
            return [
                'summary' => 'Chưa thu thập đủ dữ liệu phản hồi để AI đưa ra tóm tắt.',
                'strengths' => ['Nêu cao tinh thần cải thiện dịch vụ.'],
                'weaknesses' => ['Chưa có thông tin phản hồi từ khách hàng.'],
                'recommendations' => ['Kích hoạt gợi ý khách hàng quét mã QR gửi feedback sau mỗi đơn hàng hoàn thành.'],
            ];
        }

        $strengths = [];
        $weaknesses = [];
        $recommendations = [];

        // Dynamic synthesis based on average score
        if ($avg >= 4.0) {
            $summary = "Hệ thống đang hoạt động rất tốt với mức đánh giá trung bình cao ($avg/5★). Trải nghiệm gọi món QR và hương vị ẩm thực nhận về phản hồi tích cực áp đảo.";
            $strengths[] = 'Trải nghiệm đặt món QR nhanh chóng, thuận tiện tại bàn.';
            $strengths[] = 'Chất lượng món ăn và khẩu vị đáp ứng rất tốt kỳ vọng của thực khách.';
            $weaknesses[] = 'Một số phàn nàn nhỏ về tốc độ gửi xe hoặc không gian ồn ào lúc cao điểm.';
            $recommendations[] = 'Duy trì chất lượng dịch vụ hiện tại. Triển khai thêm chương trình tri ân (Mã giảm giá) cho khách hàng để thúc đẩy đánh giá 5★.';
            $recommendations[] = 'Tối ưu hóa sơ đồ sắp xếp bàn ăn để giảm thiểu tiếng ồn trong không gian khép kín.';
        } elseif ($avg >= 3.0) {
            $summary = "Mức độ hài lòng của khách hàng ở mức trung bình khá ($avg/5★). Cần chú ý cải thiện thời gian chuẩn bị món ăn tại bếp và thái độ của một số nhân viên.";
            $strengths[] = 'Món ăn có hương vị ổn định, mức giá tương xứng chất lượng.';
            $weaknesses[] = 'Thời gian chuẩn bị món ăn tại bếp vào cuối tuần bị trễ và sót đơn.';
            $weaknesses[] = 'Tương tác phục vụ tại bàn chưa thực sự nhiệt tình.';
            $recommendations[] = 'Chuẩn hóa quy trình chế biến của nhà bếp để giảm thiểu thời gian chờ xuống dưới 15 phút.';
            $recommendations[] = 'Tăng cường huấn luyện nghiệp vụ phục vụ bàn và thái độ chào đón thực khách.';
        } else {
            $summary = "Cảnh báo đỏ: Mức độ hài lòng khách hàng rất thấp ($avg/5★). Có nhiều vấn đề nghiêm trọng liên quan đến thái độ thu ngân và vệ sinh an toàn thực phẩm cần giải quyết khẩn cấp.";
            $strengths[] = 'Hệ thống gọi món hoạt động đúng nghiệp vụ kỹ thuật.';
            $weaknesses[] = 'Thái độ nhân viên thu ngân và nhân viên bàn gây bức xúc cho thực khách.';
            $weaknesses[] = 'Thời gian phục vụ quá chậm và có sự cố liên quan đến vệ sinh món ăn.';
            $recommendations[] = 'Kiểm điểm nghiêm khắc thái độ của nhân viên ca trực bị phàn nàn và chấn chỉnh lập tức.';
            $recommendations[] = 'Yêu cầu kiểm tra toàn diện vệ sinh khu vực bếp và quy trình chế biến nguyên liệu đầu vào.';
        }

        return [
            'summary' => $summary,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'recommendations' => $recommendations,
        ];
    }
}
