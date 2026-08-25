<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Services\AnalyticsServiceClient;
use App\Services\CircuitBreaker;
use App\Services\FraudDetectionService;
use App\Services\PromotionApplicationService;
use App\Services\PromotionStackingService;
use App\Services\QrCodeService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function __construct(
        private FraudDetectionService $fraudService,
        private PromotionApplicationService $promotionApplication,
        private PromotionStackingService $promotionStacking,
    ) {}

    /**
     * Hiển thị danh sách khuyến mãi & Dashboard kiểm toán gian lận AI.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('view_report'), 403);

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'Khuyến mãi',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $user->restaurant_id;
        $canManagePrices = $user->isOwner() || $user->isSuperAdmin();

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'status' => (string) $request->input('status', 'all'),
            // 'all' = mọi phạm vi, 'chain' = chỉ mã toàn chuỗi, hoặc id chi nhánh.
            'branch' => (string) $request->input('branch', 'all'),
        ];
        $perPage = 20;

        // 1. Lấy danh sách khuyến mãi.
        // Không lọc theo chi nhánh đang chọn: Owner cần nhìn thấy toàn bộ mã của
        // nhà hàng để biết mã nào bị khoá vào chi nhánh nào (cột "Chi nhánh" bên
        // dưới nói rõ điều đó). Bù lại, có bộ lọc riêng trên UI.
        $query = Promotion::where('restaurant_id', $restaurantId)
            ->with(['creator', 'approver', 'branch'])
            ->withCount('usages')
            ->withSum('usages as usages_discount_total', 'discount_amount');

        if ($filters['search'] !== '') {
            $keyword = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($keyword): void {
                $q->where('name', 'like', $keyword)->orWhere('code', 'like', $keyword);
            });
        }

        if ($filters['branch'] === 'chain') {
            $query->whereNull('branch_id');
        } elseif ($filters['branch'] !== 'all' && ctype_digit($filters['branch'])) {
            $query->where('branch_id', (int) $filters['branch']);
        }

        $promotions = $query->latest()->get()
            ->map(fn (Promotion $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'type' => $p->type,
                'value' => (float) $p->value,
                'min_order_amount' => (float) $p->min_order_amount,
                'max_discount_amount' => (float) $p->max_discount_amount,
                // Chuỗi đã format dùng để HIỂN THỊ; bản *_input dùng cho
                // <input type="datetime-local"> — trước đây form sửa nhận chuỗi
                // d/m/Y nên ô ngày trống trơn và request luôn bị rớt validate.
                'start_date' => $p->start_date?->format('d/m/Y H:i'),
                'end_date' => $p->end_date?->format('d/m/Y H:i'),
                'start_date_input' => $p->start_date?->format('Y-m-d\TH:i'),
                'end_date_input' => $p->end_date?->format('Y-m-d\TH:i'),
                'is_active' => (bool) $p->is_active,
                'is_approved' => (bool) $p->is_approved,
                'status' => $p->operationalStatus(),
                'branch_id' => $p->branch_id,
                'branch_name' => $p->branch?->name,
                'budget_cap' => $p->budget_cap !== null ? (float) $p->budget_cap : null,
                'budget_spent' => (float) $p->budget_spent,
                'auto_deactivate_on_budget' => (bool) $p->auto_deactivate_on_budget,
                'is_stackable' => (bool) $p->is_stackable,
                'stacking_priority' => (int) $p->stacking_priority,
                'stacking_group' => $p->stacking_group,
                'conditions' => $p->conditions,
                'usage_limit' => $p->usage_limit,
                'usage_limit_per_customer' => $p->usage_limit_per_customer,
                'usage_count' => (int) ($p->usages_count ?? 0),
                'usage_discount_total' => (float) ($p->usages_discount_total ?? 0),
                'created_by_name' => $p->creator?->name ?? 'Hệ thống',
                'approved_by_name' => $p->approver?->name ?? '—',
                'created_by_id' => $p->created_by,
            ]);

        // Đếm trên tập CHƯA lọc — nếu tính sau khi lọc thì banner nhắc việc sẽ
        // báo 0 ngay khi người dùng chọn một trạng thái khác.
        $summary = [
            'total' => $promotions->count(),
            'expired' => $promotions->where('status', Promotion::STATUS_EXPIRED)->count(),
            'pending_approval' => $promotions->where('status', Promotion::STATUS_PENDING)->count(),
            'exhausted' => $promotions->where('status', Promotion::STATUS_EXHAUSTED)->count(),
            'running' => $promotions->where('status', Promotion::STATUS_RUNNING)->count(),
        ];

        if ($filters['status'] !== 'all') {
            $promotions = $promotions->where('status', $filters['status'])->values();
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginated = new LengthAwarePaginator(
            $promotions->forPage($page, $perPage)->values(),
            $promotions->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        // 2. Lấy dữ liệu kiểm toán gian lận & cảnh báo đỏ từ FraudDetectionService
        $start = now()->subDays(30)->toDateString();
        $end = now()->toDateString();

        $fraudAlerts = $this->fraudService->detectAiFraudAlerts($restaurantId, $start, $end);

        // Lọc ngay trong SQL thay vì cắt 100 log gần nhất rồi mới lọc ở PHP.
        $voucherLogs = $this->fraudService->getVoucherAuditLogs($restaurantId)['logs'] ?? [];

        // 3. Danh sách món ăn đang bán (để map giá thật khi tạo Combo nhanh từ gợi ý AI)
        $products = Product::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get(['id', 'name', 'price'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
            ]);

        // 4. Chi nhánh — để Owner chọn phạm vi áp dụng một cách tường minh thay vì
        // bị trait BelongsToRestaurant gán ngầm theo thanh "Phạm vi dữ liệu".
        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (RestaurantBranch $b) => ['id' => $b->id, 'name' => $b->name]);

        return Inertia::render('promotions/Index', [
            'promotions' => $paginated->items(),
            // Nút "In phiếu QR" phải in mọi mã khớp bộ lọc, không chỉ 20 dòng
            // của trang đang xem.
            'printableIds' => $promotions->filter(fn (array $p) => $p['code'] !== null)
                ->pluck('id')
                ->values()
                ->all(),
            'pagination' => [
                'links' => $paginated->linkCollection()->toArray(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
            'summary' => $summary,
            'fraudAlerts' => $fraudAlerts,
            'voucherLogs' => $voucherLogs,
            'products' => $products,
            'branches' => $branches,
            'filters' => $filters,
            'canManagePrices' => $canManagePrices,
            'canCreatePromotions' => $user->can('manage_orders'),
            'canRunAnalytics' => $user->can('view_report'),
            'activeBranchId' => app(TenantContext::class)->activeBranchId(),
        ]);
    }

    /**
     * Tạo nhanh một Combo món ăn (sản phẩm ghép) từ gợi ý phân tích giỏ hàng AI.
     * Combo được lưu thành một món ăn thật trong nhóm "Combo" của thực đơn.
     */
    public function storeCombo(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);

        $restaurantId = $user->restaurant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'item_a_id' => ['required', 'integer', TenantRule::exists('products')],
            'item_b_id' => ['required', 'integer', 'different:item_a_id', TenantRule::exists('products')],
            'combo_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $itemA = Product::where('restaurant_id', $restaurantId)->with('recipes')->findOrFail($data['item_a_id']);
        $itemB = Product::where('restaurant_id', $restaurantId)->with('recipes')->findOrFail($data['item_b_id']);

        if ((float) $data['combo_price'] >= ((float) $itemA->price + (float) $itemB->price)) {
            return back()->withErrors(['combo_price' => 'Giá combo phải rẻ hơn tổng giá bán lẻ của các món thành phần ('.number_format((float) $itemA->price + (float) $itemB->price).'đ).']);
        }

        $comboCategory = ProductCategory::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'name' => 'Combo'],
            [
                'slug' => 'combo-'.Str::lower(Str::random(4)),
                'description' => 'Các combo món ăn kết hợp được tạo từ gợi ý phân tích giỏ hàng AI.',
                'display_order' => ProductCategory::where('restaurant_id', $restaurantId)->count() + 1,
                'status' => 'active',
            ]
        );

        // Gộp định lượng TRƯỚC khi ghi. product_recipes có unique(product_id,
        // ingredient_id): hai món dùng chung bất kỳ nguyên liệu nào (dầu ăn,
        // muối, hành, tỏi...) sẽ khiến lần insert thứ hai văng lỗi 500 — mà món
        // combo thì đã kịp tạo, để lại rác trong thực đơn vì không có transaction.
        $mergedRecipes = $this->mergeComboRecipes($itemA->recipes->concat($itemB->recipes));

        if ($mergedRecipes instanceof RedirectResponse) {
            return $mergedRecipes;
        }

        $comboProduct = DB::transaction(function () use ($restaurantId, $comboCategory, $data, $itemA, $itemB, $mergedRecipes) {
            $comboProduct = Product::create([
                'restaurant_id' => $restaurantId,
                'category_id' => $comboCategory->id,
                'code' => 'COMBO-'.Str::upper(Str::random(6)),
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
                'price' => $data['combo_price'],
                'description' => $data['notes'] ?? "Combo gồm {$itemA->name} và {$itemB->name}.",
                'is_active' => true,
                'is_available' => true,
                'track_inventory' => (bool) ($itemA->track_inventory || $itemB->track_inventory),
            ]);

            foreach ($mergedRecipes as $recipe) {
                ProductRecipe::create([
                    'restaurant_id' => $restaurantId,
                    'product_id' => $comboProduct->id,
                    'ingredient_id' => $recipe['ingredient_id'],
                    'unit_id' => $recipe['unit_id'],
                    'quantity' => $recipe['quantity'],
                    'waste_rate' => $recipe['waste_rate'],
                ]);
            }

            return $comboProduct;
        });

        return back()->with('success', "Đã tạo Combo \"{$comboProduct->name}\" và thêm vào thực đơn ở nhóm \"Combo\".");
    }

    /**
     * Gộp định lượng của hai món thành phần theo từng nguyên liệu.
     *
     * Cùng một nguyên liệu có thể được hai công thức khai báo bằng đơn vị khác
     * nhau (200g và 0.3kg), nên quy đổi về đơn vị của dòng đầu tiên theo đúng
     * công thức conversion_factor_to_base mà các báo cáo giá vốn đang dùng.
     *
     * @param  Collection<int, ProductRecipe>  $recipes
     * @return list<array<string, mixed>>|RedirectResponse
     */
    private function mergeComboRecipes($recipes)
    {
        $units = Unit::whereIn('id', $recipes->pluck('unit_id')->unique()->filter())
            ->get()
            ->keyBy('id');

        $merged = [];

        foreach ($recipes as $recipe) {
            $ingredientId = (int) $recipe->ingredient_id;
            $quantity = (float) $recipe->quantity;
            $wasteRate = (float) ($recipe->waste_rate ?? 0);

            if (! isset($merged[$ingredientId])) {
                $merged[$ingredientId] = [
                    'ingredient_id' => $ingredientId,
                    'unit_id' => $recipe->unit_id,
                    'quantity' => $quantity,
                    'waste_rate' => $wasteRate,
                ];

                continue;
            }

            $targetUnit = $units->get($merged[$ingredientId]['unit_id']);
            $sourceUnit = $units->get($recipe->unit_id);

            if ($targetUnit && $sourceUnit && $targetUnit->type !== $sourceUnit->type) {
                return back()->withErrors([
                    'combo_price' => "Hai món dùng chung một nguyên liệu nhưng khai báo bằng hai loại đơn vị không quy đổi được ({$sourceUnit->symbol} và {$targetUnit->symbol}). Vui lòng chuẩn hoá định lượng trước khi tạo combo.",
                ]);
            }

            $sourceFactor = (float) ($sourceUnit->conversion_factor_to_base ?? 1) ?: 1.0;
            $targetFactor = (float) ($targetUnit->conversion_factor_to_base ?? 1) ?: 1.0;

            $merged[$ingredientId]['quantity'] += $quantity * ($sourceFactor / $targetFactor);
            // Giữ tỉ lệ hao hụt thận trọng nhất trong hai công thức.
            $merged[$ingredientId]['waste_rate'] = max($merged[$ingredientId]['waste_rate'], $wasteRate);
        }

        return array_values($merged);
    }

    /**
     * Tạo mới chương trình khuyến mãi/voucher.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders'), 403);

        $data = $this->validatePromotionPayload($request);

        if ($error = $this->validateDiscountShape($data)) {
            return back()->withErrors($error);
        }

        $restaurantId = $user->restaurant_id;

        // Nếu có mã code, kiểm tra xem có trùng lặp trong cùng nhà hàng không
        if (! empty($data['code'])) {
            $exists = Promotion::where('restaurant_id', $restaurantId)
                ->where('code', strtoupper($data['code']))
                ->exists();

            if ($exists) {
                return back()->withErrors(['code' => 'Mã khuyến mãi này đã tồn tại trong nhà hàng của bạn.']);
            }
        }

        // Quy trình duyệt: Owner tạo tự duyệt, Manager tạo cần phê duyệt
        $isOwner = $user->isOwner() || $user->isSuperAdmin();

        Promotion::create(array_merge($this->promotionAttributes($data), [
            'restaurant_id' => $restaurantId,
            'is_active' => true,
            'is_approved' => $isOwner,
            'created_by' => $user->id,
            'approved_by' => $isOwner ? $user->id : null,
        ]));

        return back()->with('success', $isOwner
            ? 'Đã tạo và kích hoạt chương trình khuyến mãi.'
            : 'Đã tạo chương trình khuyến mãi. Vui lòng chờ Chủ nhà hàng phê duyệt.');
    }

    /**
     * Cập nhật chương trình khuyến mãi/voucher đã tạo.
     * Sửa đổi nội dung sẽ yêu cầu phê duyệt lại nếu người sửa không phải Owner.
     */
    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders'), 403);
        abort_if($promotion->restaurant_id !== $user->restaurant_id, 403);

        $data = $this->validatePromotionPayload($request);

        if ($error = $this->validateDiscountShape($data)) {
            return back()->withErrors($error);
        }

        if (! empty($data['code'])) {
            $exists = Promotion::where('restaurant_id', $promotion->restaurant_id)
                ->where('code', strtoupper($data['code']))
                ->where('id', '!=', $promotion->id)
                ->exists();

            if ($exists) {
                return back()->withErrors(['code' => 'Mã khuyến mãi này đã tồn tại trong nhà hàng của bạn.']);
            }
        }

        // Không hạ ngân sách xuống dưới mức đã tiêu — nếu không, isBudgetExhausted()
        // lập tức đúng và mã "chết" mà Owner không hiểu vì sao.
        if (
            ($data['budget_cap'] ?? null) !== null
            && (float) $data['budget_cap'] < (float) $promotion->budget_spent
        ) {
            return back()->withErrors([
                'budget_cap' => 'Ngân sách không được nhỏ hơn số đã chi ('.number_format((float) $promotion->budget_spent).'đ).',
            ]);
        }

        // can('approve_requests') cũng đúng với vai trò manager, nên dùng nó ở
        // đây đồng nghĩa Quản lý tự duyệt được khuyến mãi của chính mình —
        // trái với quy định "mã khuyến mãi toàn hệ thống không giao cho Quản lý".
        $isOwner = $user->isOwner() || $user->isSuperAdmin();

        $promotion->update(array_merge($this->promotionAttributes($data), [
            // Manager sửa nội dung thì cần Owner phê duyệt lại; Owner sửa thì giữ trạng thái đã duyệt.
            'is_approved' => $isOwner,
            'approved_by' => $isOwner ? $user->id : null,
        ]));

        return back()->with('success', $isOwner
            ? 'Đã cập nhật chương trình khuyến mãi.'
            : 'Đã cập nhật chương trình khuyến mãi. Vui lòng chờ Chủ nhà hàng phê duyệt lại.');
    }

    /**
     * Bộ rule dùng chung cho store/update — trước đây hai chỗ chép tay và đã
     * lệch nhau (update validate budget/stacking nhưng không hề lưu chúng).
     *
     * @return array<string, mixed>
     */
    private function validatePromotionPayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type' => ['required', 'in:percent,fixed_amount'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'branch_id' => ['nullable', 'integer', TenantRule::exists('restaurant_branches')],
            'budget_cap' => ['nullable', 'numeric', 'min:0'],
            'auto_deactivate_on_budget' => ['nullable', 'boolean'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'is_stackable' => ['nullable', 'boolean'],
            'stacking_priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'stacking_group' => ['nullable', 'string', 'max:50'],
            // Trước đây chỉ là 'array' nên payload rác cũng lọt xuống DB rồi âm
            // thầm bị PromotionStackingService bỏ qua. Ràng đúng 4 điều kiện mà
            // validateConditions() thực sự hiểu.
            'conditions' => ['nullable', 'array'],
            'conditions.day_of_week' => ['nullable', 'array'],
            'conditions.day_of_week.*' => ['integer', 'between:1,7'],
            'conditions.time_range' => ['nullable', 'array'],
            'conditions.time_range.start' => ['nullable', 'date_format:H:i'],
            'conditions.time_range.end' => ['nullable', 'date_format:H:i'],
            'conditions.min_items' => ['nullable', 'integer', 'min:1'],
            'conditions.first_order_only' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'Mã voucher chỉ được chứa chữ, số, dấu gạch ngang và gạch dưới.',
            'conditions.day_of_week.*.between' => 'Thứ trong tuần không hợp lệ.',
            'conditions.time_range.start.date_format' => 'Giờ bắt đầu phải theo dạng HH:MM.',
            'conditions.time_range.end.date_format' => 'Giờ kết thúc phải theo dạng HH:MM.',
        ]);
    }

    /**
     * Ràng buộc nghiệp vụ của giá trị giảm.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    private function validateDiscountShape(array $data): ?array
    {
        $value = (float) $data['value'];

        if ($data['type'] === 'percent') {
            if ($value < 1 || $value > 100) {
                return ['value' => 'Giá trị giảm giá phần trăm phải từ 1% đến 100%.'];
            }

            return null;
        }

        // fixed_amount: bắt buộc có đơn tối thiểu và mức giảm phải NHỎ HƠN nó.
        // Rule cũ là `value > min_order` → với min_order mặc định 0 thì không thể
        // tạo nổi voucher tiền mặt, còn khi bằng nhau thì đơn về 0đ vẫn hợp lệ.
        $minOrder = (float) ($data['min_order_amount'] ?? 0);

        if ($minOrder <= 0) {
            return ['min_order_amount' => 'Voucher khấu trừ tiền mặt bắt buộc phải có giá trị đơn hàng tối thiểu.'];
        }

        if ($value <= 0) {
            return ['value' => 'Số tiền khấu trừ phải lớn hơn 0.'];
        }

        if ($value >= $minOrder) {
            return ['value' => 'Số tiền khấu trừ phải nhỏ hơn giá trị đơn hàng tối thiểu ('.number_format($minOrder).'đ), nếu không đơn hàng sẽ về 0đ.'];
        }

        return null;
    }

    /**
     * Map payload đã validate sang cột DB. Dùng chung cho store/update để không
     * còn cảnh update() validate một đằng, lưu một nẻo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function promotionAttributes(array $data): array
    {
        $isPercent = $data['type'] === 'percent';

        return [
            'name' => $data['name'],
            'code' => ! empty($data['code']) ? strtoupper($data['code']) : null,
            'type' => $data['type'],
            'value' => $data['value'],
            'min_order_amount' => $data['min_order_amount'] ?? 0,
            // max_discount_amount chỉ có nghĩa với loại %; với loại tiền mặt nó
            // không được logic tính tiền dùng tới, để lại chỉ gây hiểu nhầm.
            'max_discount_amount' => $isPercent ? ($data['max_discount_amount'] ?? 0) : 0,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            // Gán tường minh (kể cả null = toàn chuỗi) để trait BelongsToRestaurant
            // không tự điền chi nhánh đang active vào một cách âm thầm.
            'branch_id' => $data['branch_id'] ?? null,
            'budget_cap' => $data['budget_cap'] ?? null,
            'auto_deactivate_on_budget' => (bool) ($data['auto_deactivate_on_budget'] ?? false),
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
            'is_stackable' => (bool) ($data['is_stackable'] ?? false),
            'stacking_priority' => $data['stacking_priority'] ?? 0,
            'stacking_group' => $data['stacking_group'] ?? null,
            'conditions' => $this->normalizeConditions($data['conditions'] ?? null),
        ];
    }

    /**
     * Bỏ các điều kiện rỗng. Nếu giữ nguyên, DB sẽ có những giá trị như
     * {"day_of_week": [], "min_items": null} — validateConditions() coi chúng là
     * "không có điều kiện" nhưng bảng lại hiển thị như thể đang có ràng buộc.
     *
     * @param  array<string, mixed>|null  $conditions
     * @return array<string, mixed>|null
     */
    private function normalizeConditions(?array $conditions): ?array
    {
        if ($conditions === null) {
            return null;
        }

        $clean = [];

        if (! empty($conditions['day_of_week'])) {
            $clean['day_of_week'] = array_values(array_unique(array_map(
                'intval',
                $conditions['day_of_week'],
            )));
            sort($clean['day_of_week']);
        }

        $start = $conditions['time_range']['start'] ?? null;
        $end = $conditions['time_range']['end'] ?? null;
        if ($start && $end) {
            $clean['time_range'] = ['start' => $start, 'end' => $end];
        }

        if (! empty($conditions['min_items'])) {
            $clean['min_items'] = (int) $conditions['min_items'];
        }

        if (! empty($conditions['first_order_only'])) {
            $clean['first_order_only'] = true;
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * Xóa chương trình khuyến mãi/voucher.
     */
    public function destroy(Request $request, Promotion $promotion): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        abort_if($promotion->restaurant_id !== $user->restaurant_id, 403);

        // Xoá cứng sẽ cascade mất luôn lịch sử promotion_usages — tức là mất
        // bằng chứng đối soát cho các đơn đã áp mã. Đã phát sinh giao dịch thì
        // chỉ cho tạm dừng.
        if ($promotion->usages()->exists()) {
            return back()->withErrors([
                'delete' => 'Chương trình đã phát sinh giao dịch nên không thể xóa. Hãy tạm dừng để giữ lịch sử đối soát.',
            ]);
        }

        $promotion->delete();

        return back()->with('success', 'Đã xóa chương trình khuyến mãi.');
    }

    /**
     * Bật/Tắt nhanh chương trình khuyến mãi.
     */
    public function toggleActive(Request $request, Promotion $promotion): RedirectResponse
    {
        abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin(), 403);
        abort_if($promotion->restaurant_id !== $request->user()->restaurant_id, 403);

        $turningOn = ! $promotion->is_active;

        // Bật một chương trình chưa duyệt chỉ tạo ra badge "Hoạt động" giả:
        // applyToOrder vẫn từ chối vì is_approved = false.
        if ($turningOn && ! $promotion->is_approved) {
            return back()->withErrors(['is_active' => 'Chương trình chưa được phê duyệt nên chưa thể kích hoạt.']);
        }

        if ($turningOn && $promotion->isExpired()) {
            return back()->withErrors(['is_active' => 'Chương trình đã quá hạn kết thúc. Hãy gia hạn ngày kết thúc trước khi bật lại.']);
        }

        $promotion->update(['is_active' => $turningOn]);

        return back()->with('success', $turningOn
            ? 'Đã kích hoạt chương trình khuyến mãi.'
            : 'Đã tạm dừng chương trình khuyến mãi.');
    }

    /**
     * Phê duyệt chương trình khuyến mãi (Chỉ Owner).
     */
    public function approve(Request $request, Promotion $promotion): RedirectResponse
    {
        abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin(), 403);
        abort_if($promotion->restaurant_id !== $request->user()->restaurant_id, 403);
        // Người tạo không tự duyệt chương trình của mình.
        abort_if(
            (int) $promotion->created_by === (int) $request->user()->id && ! $request->user()->isSuperAdmin(),
            403,
            'Bạn không thể tự duyệt chương trình khuyến mãi do chính mình tạo.',
        );

        if ($promotion->isExpired()) {
            return back()->withErrors([
                'is_approved' => 'Chương trình đã quá hạn kết thúc, duyệt cũng không áp dụng được. Hãy gia hạn trước.',
            ]);
        }

        $promotion->update([
            'is_approved' => true,
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Đã phê duyệt chương trình khuyến mãi thành công.');
    }

    /**
     * API dành cho Cashier để áp dụng mã khuyến mãi vào Order.
     */
    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();
        // Trước đây action này KHÔNG có gate nào: bất kỳ tài khoản nào trong nhà
        // hàng (bếp, phục vụ, kho) cũng POST được để giảm giá đơn bất kỳ — đúng
        // lỗ hổng mà cả màn hình này tuyên bố đang chặn.
        abort_unless(
            $user->can('manage_orders') || $user->can('process_payments') || $user->hasRole('cashier'),
            403,
            'Bạn không có quyền áp mã giảm giá lên đơn hàng.',
        );

        $data = $request->validate([
            'order_id' => ['required', TenantRule::exists('orders')],
            'code' => ['required', 'string'],
            'bypass_code' => ['nullable', 'string'],
        ]);

        $result = $this->promotionApplication->applyToOrder(
            $user->restaurant_id,
            $user,
            (int) $data['order_id'],
            $data['code'],
            $data['bypass_code'] ?? null,
        );

        if (! $result['success']) {
            $body = ['message' => $result['message']];
            if ($result['status'] === 'requires_bypass') {
                $body['status'] = 'requires_bypass';
            }

            return response()->json($body, 422);
        }

        return response()->json(array_merge(['message' => $result['message']], $result['data']));
    }

    /**
     * Trả về các voucher mà thu ngân có thể chọn cho đúng đơn hiện tại.
     * Danh sách được lọc theo chi nhánh, thời gian, ngân sách và điều kiện đơn.
     */
    public function availableForCashier(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->hasRole('cashier'), 403);

        $data = $request->validate([
            'order_id' => ['required', 'integer', TenantRule::exists('orders')],
        ]);

        $branchId = $tenantContext->activeBranchId();
        abort_if($branchId === null, 422, 'POS phải được mở trong một chi nhánh cụ thể.');
        abort_unless($user->canAccessBranch($branchId), 403);

        $order = Order::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $branchId)
            ->with('items')
            ->findOrFail((int) $data['order_id']);

        $customerUsageByPromotion = $order->customer_id
            ? PromotionUsage::where('restaurant_id', $user->restaurant_id)
                ->where('customer_id', $order->customer_id)
                ->selectRaw('promotion_id, COUNT(*) as total')
                ->groupBy('promotion_id')
                ->pluck('total', 'promotion_id')
                ->all()
            : [];

        $now = now();
        $promotions = Promotion::where('restaurant_id', $user->restaurant_id)
            ->withCount('usages')
            ->whereNotNull('code')
            ->where('is_active', true)
            ->where('is_approved', true)
            ->where(function ($query) use ($branchId): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->orderBy('stacking_priority', 'desc')
            ->orderBy('name')
            ->get()
            ->filter(function (Promotion $promotion) use ($order, $customerUsageByPromotion): bool {
                // Giới hạn theo từng khách cũng phải lọc ở đây, nếu không mã vẫn
                // hiện trong danh sách rồi mới báo lỗi lúc thu ngân bấm Áp dụng.
                $perCustomerReached = $promotion->usage_limit_per_customer !== null
                    && $order->customer_id !== null
                    && ($customerUsageByPromotion[$promotion->id] ?? 0) >= (int) $promotion->usage_limit_per_customer;

                return (float) $order->subtotal >= (float) $promotion->min_order_amount
                    && ! $promotion->isBudgetExhausted()
                    && ! $promotion->isUsageLimitReached()
                    && ! $perCustomerReached
                    && $this->promotionStacking->validateConditions($promotion, $order);
            })
            ->values()
            ->map(fn (Promotion $promotion): array => [
                'id' => $promotion->id,
                'code' => $promotion->code,
                'name' => $promotion->name,
                'type' => $promotion->type,
                'value' => (float) $promotion->value,
                'min_order_amount' => (float) $promotion->min_order_amount,
                'max_discount_amount' => (float) $promotion->max_discount_amount,
                'discount_label' => $promotion->type === 'percent'
                    ? ((float) $promotion->value).'%'
                    : number_format((float) $promotion->value).'đ',
            ]);

        return response()->json(['promotions' => $promotions]);
    }

    public function validatePromotion(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->can('manage_orders') || $user->can('process_payments') || $user->hasRole('cashier'),
            403,
        );

        $data = $request->validate([
            'code' => ['required', 'string'],
            'order_id' => ['nullable', TenantRule::exists('orders')],
        ]);

        return response()->json($this->promotionApplication->validateForOrder($user->restaurant_id, $data['code']));
    }

    /**
     * API phân tích giỏ hàng (Market Basket Analysis) - Gọi FastAPI hoặc Fallback.
     */
    public function generateQr(Request $request, Promotion $promotion): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('view_report'), 403);
        abort_unless($promotion->restaurant_id === $user->restaurant_id, 403);
        abort_if($promotion->code === null, 422, 'Chương trình này không có mã voucher nên không tạo được QR.');

        $qrService = app(QrCodeService::class);

        // QR mã hoá CHÍNH MÃ VOUCHER dạng text, không phải URL.
        // URL cũ (/customer/coupon/claim/...) không tồn tại trong routes — khách
        // quét sẽ nhận 404. Route thật là customer/coupons/{restaurant}/{phone}
        // và bắt buộc token đã ký gửi qua SMS, nên không thể nhúng vào poster.
        // Mã text thì máy quét của POS đọc thẳng vào ô nhập voucher.
        $payload = $promotion->code;
        $svg = $qrService->renderSvg($payload, 300);

        $filename = "promo_{$promotion->restaurant_id}_{$promotion->code}";
        $path = $qrService->generateAndStore($payload, $filename);

        return response()->json([
            'svg' => $svg,
            'download_url' => asset("storage/{$path}"),
            'code' => $promotion->code,
            'payload' => $payload,
        ]);
    }

    public function printQrSheet(Request $request): \Illuminate\Http\Response
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('view_report'), 403);

        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $promotions = Promotion::where('restaurant_id', $user->restaurant_id)
            ->whereIn('id', $ids)
            ->whereNotNull('code')
            ->get();

        abort_if($promotions->isEmpty(), 422, 'Không có chương trình nào có mã voucher để in.');

        $qrService = app(QrCodeService::class);
        $qrCodes = [];

        foreach ($promotions as $promo) {
            $qrCodes[] = [
                'name' => $promo->name,
                'code' => $promo->code,
                'discount' => $promo->type === 'percent' ? "{$promo->value}%" : number_format($promo->value).'₫',
                'svg' => $qrService->renderSvg($promo->code, 200),
            ];
        }

        $html = view('prints.promotion-qr-sheet', ['qrCodes' => $qrCodes, 'restaurant' => $user->restaurant?->name ?? ''])->render();

        return response($html)->header('Content-Type', 'text/html');
    }

    public function getBasketAnalysis(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('view_report'), 403);

        $restaurantId = $user->restaurant_id;

        // 1. Thu thập dữ liệu đơn hàng hoàn thành
        $orders = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->with(['items.product'])
            ->latest()
            ->take(1000) // Lấy tối đa 1000 đơn hàng gần đây để phân tích
            ->get();

        $ordersData = [];
        foreach ($orders as $order) {
            $items = $order->items
                ->map(fn ($item) => $item->product?->name)
                ->filter()
                ->values()
                ->toArray();

            if (count($items) > 0) {
                $ordersData[] = [
                    'order_id' => $order->id,
                    'items' => $items,
                ];
            }
        }

        if (count($ordersData) === 0) {
            return response()->json([
                'total_orders' => 0,
                'rules' => [],
                'message' => 'Không đủ dữ liệu hóa đơn hoàn thành để chạy phân tích giỏ hàng.',
            ]);
        }

        // 2. Gửi request sang Python FastAPI microservice (port 8003), qua CircuitBreaker
        // dùng chung (thay cờ Cache::has('analytics_service_offline') thủ công cũ — không
        // có failure-threshold/backoff, không hiển thị được trạng thái trên dashboard vận hành).
        $url = config('services.analytics.url').'/api/analytics/basket-analysis';

        $result = app(CircuitBreaker::class)->for('analytics_service')->attempt(
            function () use ($url, $ordersData) {
                $response = Http::timeout(4) // Timeout ngắn 4 giây
                    ->withHeaders(app(AnalyticsServiceClient::class)->authHeaders())
                    ->post($url, [
                        'orders' => $ordersData,
                        'min_support' => 0.01,
                        'min_confidence' => 0.05,
                    ]);

                if ($response->successful()) {
                    $result = $response->json();
                    $result['source'] = 'Python Service (FastAPI + Pandas)';

                    return $result;
                }

                throw new \RuntimeException('PromotionController::getBasketAnalysis: phản hồi không hợp lệ từ analytics service');
            },
            fn () => $this->runFallbackAnalysis($ordersData)
        );

        return response()->json($result);
    }

    /**
     * Thuật toán fallback phân tích giỏ hàng trong PHP (Apriori & Association Rules).
     */
    private function runFallbackAnalysis(array $ordersData): array
    {
        $totalOrders = count($ordersData);
        if ($totalOrders === 0) {
            return ['total_orders' => 0, 'rules' => []];
        }

        $itemCounts = [];
        $pairCounts = [];

        // Đếm tần suất món đơn và cặp món
        foreach ($ordersData as $order) {
            $uniqueProducts = array_values(array_unique($order['items']));
            foreach ($uniqueProducts as $item) {
                $itemCounts[$item] = ($itemCounts[$item] ?? 0) + 1;
            }

            $itemCount = count($uniqueProducts);
            for ($i = 0; $i < $itemCount; $i++) {
                for ($j = $i + 1; $j < $itemCount; $j++) {
                    $itemA = $uniqueProducts[$i];
                    $itemB = $uniqueProducts[$j];

                    // Gom nhóm thống kê cho cả hai chiều để tạo luật liên kết
                    $key1 = "{$itemA}|||{$itemB}";
                    $key2 = "{$itemB}|||{$itemA}";

                    $pairCounts[$key1] = ($pairCounts[$key1] ?? 0) + 1;
                    $pairCounts[$key2] = ($pairCounts[$key2] ?? 0) + 1;
                }
            }
        }

        $rules = [];
        foreach ($pairCounts as $key => $countAB) {
            [$itemA, $itemB] = explode('|||', $key);

            $countA = $itemCounts[$itemA] ?? 0;
            $countB = $itemCounts[$itemB] ?? 0;

            if ($countA === 0 || $countB === 0) {
                continue;
            }

            $support = $countAB / $totalOrders;
            $confidence = $countAB / $countA;
            $expectedConfidence = $countB / $totalOrders;
            $lift = $expectedConfidence > 0 ? $confidence / $expectedConfidence : 0;

            // Lọc các luật có ý nghĩa thống kê tương tự FastAPI
            if ($support >= 0.01 && $confidence >= 0.05) {
                $rules[] = [
                    'item_a' => $itemA,
                    'item_b' => $itemB,
                    'support' => round($support, 4),
                    'confidence' => round($confidence, 4),
                    'lift' => round($lift, 4),
                    'co_occurrence' => $countAB,
                ];
            }
        }

        // Sắp xếp theo Lift giảm dần,Confidence giảm dần
        usort($rules, function ($a, $b) {
            if ($a['lift'] == $b['lift']) {
                return $b['confidence'] <=> $a['confidence'];
            }

            return $b['lift'] <=> $a['lift'];
        });

        return [
            'total_orders' => $totalOrders,
            'rules' => array_slice($rules, 0, 30),
            'source' => 'Laravel Fallback Engine (Fail-safe Active)',
        ];
    }

    /**
     * API Trợ lý gợi ý Upselling tại bàn dựa trên giỏ hàng thời gian thực.
     */
    public function getUpsellSuggestion(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->can('create_orders') || $user->can('manage_orders') || $user->hasRole('cashier'),
            403,
        );

        $restaurantId = $user->restaurant_id;

        $data = $request->validate([
            'items' => ['required', 'array'],
        ]);

        $items = $data['items'];

        if (empty($items)) {
            return response()->json([
                'suggestion' => null,
                'recommended_item' => null,
                'source' => 'System',
            ]);
        }

        // 1. Gửi request sang Python FastAPI cổng 8003, qua CircuitBreaker dùng chung.
        $url = config('services.analytics.url').'/api/analytics/upsell-suggestion';

        $result = app(CircuitBreaker::class)->for('analytics_service')->attempt(
            function () use ($url, $items) {
                $response = Http::timeout(2) // Timeout cực ngắn 2s để đảm bảo trải nghiệm POS
                    ->withHeaders(app(AnalyticsServiceClient::class)->authHeaders())
                    ->post($url, [
                        'items' => $items,
                    ]);

                if ($response->successful()) {
                    $result = $response->json();
                    $result['source'] = 'Python Service (FastAPI + Pandas)';

                    return $result;
                }

                throw new \RuntimeException('PromotionController::getUpsellSuggestion: phản hồi không hợp lệ từ analytics service');
            },
            fn () => $this->runFallbackUpsell($items, $restaurantId)
        );

        return response()->json($result);
    }

    /**
     * Thuật toán fallback tự động tính toán luật liên kết giỏ hàng và gợi ý bán thêm.
     */
    private function runFallbackUpsell(array $items, int $restaurantId): array
    {
        // Thu thập các đơn hàng hoàn thành
        $orders = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->with(['items.product'])
            ->latest()
            ->take(500)
            ->get();

        $ordersData = [];
        foreach ($orders as $order) {
            $orderItems = $order->items
                ->map(fn ($item) => $item->product?->name)
                ->filter()
                ->values()
                ->toArray();

            if (count($orderItems) > 0) {
                $ordersData[] = $orderItems;
            }
        }

        $totalOrders = count($ordersData);
        if ($totalOrders === 0) {
            return [
                'suggestion' => 'Chào mừng quý khách! Hãy chọn thêm các món ăn đặc sắc từ thực đơn.',
                'recommended_item' => null,
                'source' => 'Laravel Fallback Engine (Fail-safe Active)',
            ];
        }

        // Đếm tần suất món và các cặp món
        $itemCounts = [];
        $pairCounts = [];

        foreach ($ordersData as $order) {
            $uniqueProducts = array_unique($order);
            foreach ($uniqueProducts as $item) {
                $itemCounts[$item] = ($itemCounts[$item] ?? 0) + 1;
            }

            $itemCount = count($uniqueProducts);
            for ($i = 0; $i < $itemCount; $i++) {
                for ($j = $i + 1; $j < $itemCount; $j++) {
                    $itemA = $uniqueProducts[$i];
                    $itemB = $uniqueProducts[$j];

                    // Tạo liên kết A -> B
                    $key1 = "{$itemA}|||{$itemB}";
                    $key2 = "{$itemB}|||{$itemA}";

                    $pairCounts[$key1] = ($pairCounts[$key1] ?? 0) + 1;
                    $pairCounts[$key2] = ($pairCounts[$key2] ?? 0) + 1;
                }
            }
        }

        // Tìm quy tắc phù hợp nhất dựa trên món ăn đang có trong giỏ hàng
        $bestRule = null;
        $maxLift = 0;

        foreach ($pairCounts as $key => $countAB) {
            [$itemA, $itemB] = explode('|||', $key);

            // Chỉ xét nếu món A đang có trong giỏ hàng, và món B CHƯA có trong giỏ hàng
            if (in_array($itemA, $items) && ! in_array($itemB, $items)) {
                $countA = $itemCounts[$itemA] ?? 0;
                $countB = $itemCounts[$itemB] ?? 0;

                if ($countA === 0 || $countB === 0) {
                    continue;
                }

                $support = $countAB / $totalOrders;
                $confidence = $countAB / $countA;
                $expectedConfidence = $countB / $totalOrders;
                $lift = $expectedConfidence > 0 ? $confidence / $expectedConfidence : 0;

                if ($lift > $maxLift && $confidence >= 0.05) {
                    $maxLift = $lift;
                    $bestRule = [
                        'item_a' => $itemA,
                        'item_b' => $itemB,
                        'confidence' => $confidence,
                        'lift' => $lift,
                    ];
                }
            }
        }

        if ($bestRule) {
            $itemA = $bestRule['item_a'];
            $itemB = $bestRule['item_b'];

            // Gợi ý câu thoại thông minh kết hợp Marketing Voucher/Combo
            $suggestion = "AI đề xuất: Khách đang gọi {$itemA}, mời dùng thêm {$itemB} để được áp dụng mã giảm giá Combo ưu đãi đã cấu hình.";

            return [
                'suggestion' => $suggestion,
                'recommended_item' => $itemB,
                'source' => 'Laravel Fallback Engine (Fail-safe Active)',
            ];
        }

        // Nếu không tìm thấy luật liên kết nào cụ thể, gợi ý món bán chạy nhất chưa có trong giỏ
        arsort($itemCounts);
        foreach ($itemCounts as $item => $cnt) {
            if (! in_array($item, $items)) {
                return [
                    'suggestion' => "AI đề xuất: Món ăn đặc sắc '{$item}' đang bán rất chạy hôm nay, mời quý khách thưởng thức thêm!",
                    'recommended_item' => $item,
                    'source' => 'Laravel Fallback Engine (Fail-safe Active)',
                ];
            }
        }

        return [
            'suggestion' => 'Khách hàng đang gọi các món ăn tuyệt vời nhất của quán. Chúc quý khách ngon miệng!',
            'recommended_item' => null,
            'source' => 'Laravel Fallback Engine (Fail-safe Active)',
        ];
    }
}
