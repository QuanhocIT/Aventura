<?php

namespace App\Http\Controllers;

use App\Events\Customer\TemporaryOrderUpdated;
use App\Models\AccountReceivable;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Delivery\DeliveryDetail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RestaurantTable;
use App\Models\TemporaryOrder;
use App\Models\User;
use App\Repositories\OrderRepositoryInterface;
use App\Services\ApprovalService;
use App\Services\CdpService;
use App\Services\FinancialPostingService;
use App\Services\InventoryAvailabilityService;
use App\Services\InventoryService;
use App\Services\LoyaltyService;
use App\Services\OrderItemCancellationService;
use App\Services\OrderRefundService;
use App\Services\OrderService;
use App\Services\PolicyEnforcementService;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected OrderRepositoryInterface $orderRepository,
        protected TenantContext $tenantContext,
        protected PolicyEnforcementService $policy,
    ) {}

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('create_orders'), 403);
        $restaurantId = $user->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();
        abort_if($branchId === null, 403, 'POS phải được mở trong một chi nhánh cụ thể.');

        $categories = ProductCategory::where('restaurant_id', $restaurantId)
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->where('status', 'active')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        $products = Product::where('restaurant_id', $restaurantId)
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->where('is_active', true)
            ->where('is_available', true)
            ->sellableMenu()
            ->with(['category', 'recipes.ingredient.unit'])
            ->get();

        $availabilityService = app(InventoryAvailabilityService::class);
        $availability = $availabilityService->forProducts($products, $restaurantId, (int) $branchId);

        $products = $products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => (float) $p->price,
            'sku' => $p->sku ?? '—',
            'category_id' => $p->category_id,
            'category_name' => $p->category?->name,
            'available_portions' => $availability->get($p->id)['available_portions'] ?? null,
            'paused_until' => $p->paused_until ? $p->paused_until->toIso8601String() : null,
            'out_of_stock_until' => $p->out_of_stock_until ? $p->out_of_stock_until->toIso8601String() : null,
            'is_paused' => $p->paused_until && $p->paused_until->isFuture(),
            'is_out_of_stock' => ($availability->get($p->id)['is_sold_out'] ?? false)
                || ($p->out_of_stock_until && $p->out_of_stock_until->isFuture()),
        ]);

        $tables = RestaurantTable::where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('status', 'available')
            ->whereDoesntHave('orders', fn ($query) => $query->activeForService())
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
            ]);

        return Inertia::render('orders/Create', [
            'products' => $products,
            'categories' => $categories,
            'tables' => $tables,
        ]);
    }

    /**
     * Lưu đơn hàng mới từ giao diện POS bán hàng SPA.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('create_orders'), 403);

        $rid = $user->restaurant_id;
        $data = $request->validate([
            'channel' => ['nullable', 'in:dine_in,takeaway,delivery'],
            'table_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', "exists:customers,id,restaurant_id,{$rid}"],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.client_item_id' => ['nullable', 'string', 'max:100'],
            'guests_count' => ['nullable', 'integer', 'min:1'],
            // Delivery-specific fields
            'delivery_customer_name' => ['required_if:channel,delivery', 'nullable', 'string', 'max:255'],
            'delivery_phone' => ['required_if:channel,delivery', 'nullable', 'string', 'max:20'],
            'delivery_address' => ['required_if:channel,delivery', 'nullable', 'string', 'max:500'],
            'delivery_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'cod_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
        ]);
        $branchId = $this->tenantContext->activeBranchId();
        abort_if($branchId === null, 403, 'POS phải được mở trong một chi nhánh cụ thể.');

        if (! empty($data['table_id']) && ! RestaurantTable::where('restaurant_id', $rid)->where('branch_id', $branchId)->whereKey($data['table_id'])->exists()) {
            return back()->withErrors(['table_id' => 'Bàn không thuộc chi nhánh hiện tại.']);
        }

        $productIds = collect($data['items'])->pluck('product_id')->unique()->values();
        $validProductCount = Product::where('restaurant_id', $rid)
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->whereIn('id', $productIds)
            ->sellableMenu()
            ->count();
        if ($validProductCount !== $productIds->count()) {
            return back()->withErrors(['items' => 'Có món không thuộc thực đơn của chi nhánh hiện tại.']);
        }

        if (isset($data['table_id']) && isset($data['guests_count'])) {
            $table = RestaurantTable::find($data['table_id']);
            if ($table && (int) $data['guests_count'] > (int) $table->capacity) {
                return back()->withErrors(['guests_count' => "Số lượng khách ({$data['guests_count']}) vượt quá sức chứa tối đa của bàn {$table->name} (Tối đa {$table->capacity} chỗ). Vui lòng chọn ghép bàn hoặc chuyển bàn lớn hơn."]);
            }
        }

        // Check kitchen availability status for products
        if (isset($data['items'])) {
            $productIds = collect($data['items'])->pluck('product_id')->toArray();
            $products = Product::whereIn('id', $productIds)
                ->where('restaurant_id', $user->restaurant_id)
                ->where(function ($query) use ($branchId) {
                    $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
                })
                ->sellableMenu()
                ->get();
            foreach ($products as $product) {
                $isKitchenPaused = $product->paused_until && $product->paused_until->isFuture();
                $isKitchenOutOfStock = $product->out_of_stock_until && $product->out_of_stock_until->isFuture();
                if (! $product->is_active || ! $product->is_available || $isKitchenPaused || $isKitchenOutOfStock) {
                    return back()->withErrors(['items' => "Món ăn {$product->name} tạm thời ngừng phục vụ."]);
                }
            }
        }

        try {
            $order = DB::transaction(function () use ($data, $user) {
                $order = $this->orderService->createOrder($data, $user);

                if (($data['channel'] ?? '') === 'delivery') {
                    DeliveryDetail::create([
                        'restaurant_id' => $user->restaurant_id,
                        'order_id' => $order->id,
                        'customer_name' => $data['delivery_customer_name'],
                        'phone' => $data['delivery_phone'],
                        'address' => $data['delivery_address'],
                        'latitude' => $data['delivery_lat'] ?? null,
                        'longitude' => $data['delivery_lng'] ?? null,
                        'delivery_fee' => $data['delivery_fee'] ?? 0,
                        'cod_amount' => $data['cod_amount'] ?? 0,
                        'delivery_notes' => $data['delivery_notes'] ?? null,
                        'delivery_status' => 'pending',
                    ]);
                }

                return $order;
            });
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['items' => $exception->getMessage()]);
        }

        if ($user->can('create_orders') || url()->previous() === route('dashboard')) {
            return redirect()->back()->with('success', 'Đã gửi đơn hàng mới xuống nhà bếp thành công!');
        }

        return redirect()->route('orders.index')->with('success', 'Đã gửi đơn hàng mới xuống nhà bếp thành công!');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('process_payments') || $user->can('manage_kitchen'), 403);
        $restaurantId = $user->restaurant_id;

        $statusFilter = $request->get('status', 'all');
        $dateFilter = $request->get('date') ?: today()->toDateString();
        $branchId = $this->tenantContext->activeBranchId();

        $ordersQuery = $this->orderRepository->getOrdersQuery($restaurantId, [
            'status' => $statusFilter,
            'date' => $dateFilter,
            'branch_id' => $branchId,
        ]);

        $isKitchenOnly = $user->can('manage_kitchen') &&
            ! $user->can('create_orders') &&
            ! $user->can('manage_orders') &&
            ! $user->can('process_payments');

        if ($isKitchenOnly) {
            $ordersQuery->whereHas('items', function ($query) {
                $query->whereNotNull('served_at');
            });
        }

        // The Orders page performs its own 10-item client-side pagination and
        // consumes `orders` as a plain array. Passing the paginator object
        // directly makes Vue call array methods on LengthAwarePaginator and
        // leaves the page blank at runtime.
        $orders = $ordersQuery->paginate(50)->through(function ($o) {
            $items = $o->items
                ->reject(fn ($item) => $item->status === 'cancelled')
                ->values();
            $itemsCount = $items->count();
            $preparedCount = $items->whereNotNull('prepared_at')->count();
            $servedCount = $items->whereNotNull('served_at')->count();
            $inProgressCount = $items
                ->whereNotNull('started_preparing_at')
                ->whereNull('prepared_at')
                ->whereNull('served_at')
                ->count();
            $readyCount = $items
                ->whereNotNull('prepared_at')
                ->whereNull('served_at')
                ->count();

            // Payment may set orders.status to completed before the kitchen
            // and waiter finish. Keep the operational progress per item.
            $fulfillmentStatus = match (true) {
                $itemsCount === 0 || $servedCount === $itemsCount => 'served',
                $readyCount === $itemsCount => 'ready',
                $inProgressCount > 0 || $readyCount > 0 || $preparedCount > 0 => 'preparing',
                default => 'pending',
            };

            return [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'branch_id' => $o->branch_id,
                'branch_name' => $o->branch?->name ?? 'Chưa xác định',
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'refund_amount' => $o->refund_amount !== null ? (float) $o->refund_amount : null,
                'refund_reason' => $o->refund_reason,
                'refunded_at' => $o->refunded_at?->format('H:i - d/m/Y'),
                'refunded_by_name' => $o->refundedBy?->name,
                'channel' => $o->channel,
                'fulfillment_status' => $fulfillmentStatus,
                'table_name' => $o->table?->name,
                'area_name' => $o->table?->area?->name,
                'total_amount' => (float) $o->total_amount,
                'items_count' => $itemsCount,
                'items_prepared_count' => $preparedCount,
                'items_served_count' => $servedCount,
                'items_in_progress_count' => $inProgressCount,
                'items_ready_count' => $readyCount,
                'created_at' => $o->created_at->format('H:i'),
                'created_at_full' => $o->created_at->format('H:i:s - d/m/Y'),
                'created_at_formatted' => $o->created_at->format('H:i - d/m/Y'),
                'completed_at' => $o->completed_at?->format('H:i'),
                'completed_at_full' => $o->completed_at?->format('H:i:s - d/m/Y'),
                'completed_at_formatted' => $o->completed_at?->format('H:i - d/m/Y'),
                'note' => $o->note ?? $o->notes ?? null,
                'items' => $o->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->product?->name ?? 'Món ăn',
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) ($item->unit_price ?? 0),
                    'line_total' => (float) ($item->line_total ?? ($item->quantity * ($item->unit_price ?? 0))),
                    'notes' => $item->notes ?? null,
                    'status' => $item->status ?? null,
                    'sent_to_kitchen_at' => $item->sent_to_kitchen_at?->format('H:i'),
                    'started_preparing_at' => $item->started_preparing_at?->format('H:i'),
                    'prepared_at' => $item->prepared_at?->format('H:i'),
                    'prepared_at_formatted' => $item->prepared_at?->format('H:i - d/m/Y'),
                    'prepared_by_name' => $item->preparedBy?->name ?? null,
                    'served_at' => $item->served_at?->format('H:i'),
                    'served_at_formatted' => $item->served_at?->format('H:i - d/m/Y'),
                    'served_by_name' => $item->servedBy?->name ?? null,
                    'cancelled_at' => $item->cancelled_at?->format('H:i'),
                    'cancelled_by_name' => $item->cancelledBy?->name ?? null,
                    'cancellation_reason' => $item->cancellation_reason,
                ])->values()->all(),
                'delivery' => $o->deliveryDetail ? [
                    'customer_name' => $o->deliveryDetail->customer_name,
                    'phone' => $o->deliveryDetail->phone,
                    'address' => $o->deliveryDetail->address,
                    'fee' => (float) $o->deliveryDetail->delivery_fee,
                    'cod' => (float) $o->deliveryDetail->cod_amount,
                    'status' => $o->deliveryDetail->delivery_status,
                    'notes' => $o->deliveryDetail->delivery_notes,
                ] : null,
            ];
        })->items();

        $summary = $this->orderRepository->getSummaryStats($restaurantId, $dateFilter, $isKitchenOnly, $branchId);
        $summary['ready'] = $this->orderRepository->getOrdersQuery($restaurantId, [
            'status' => 'ready',
            'date' => $dateFilter,
            'branch_id' => $branchId,
        ])->count();

        // A QR request is stored as a temporary order until a staff member
        // confirms it. Keep rejected QR requests visible in the same date
        // view as cancelled official orders so a rejection is not mistaken
        // for a missing record.
        $cancelledQrOrdersQuery = TemporaryOrder::where('restaurant_id', $restaurantId)
            ->where('status', 'cancelled')
            ->whereDate('updated_at', $dateFilter)
            ->with(['table.area', 'branch:id,name', 'cancelledBy:id,name'])
            ->latest('updated_at');

        if ($this->tenantContext->isBranchScoped() || $this->tenantContext->isUnassigned()) {
            $this->tenantContext->applyBranchScope($cancelledQrOrdersQuery);
        }

        $cancelledQrOrders = $cancelledQrOrdersQuery->get()->map(function (TemporaryOrder $temporaryOrder) {
            $items = collect($temporaryOrder->cart_data ?? []);

            return [
                'id' => $temporaryOrder->id,
                'order_number' => 'QR-TEMP-'.$temporaryOrder->id,
                'branch_id' => $temporaryOrder->branch_id,
                'branch_name' => $temporaryOrder->branch?->name ?? 'Chưa xác định',
                'status' => 'cancelled',
                'payment_status' => 'unpaid',
                'channel' => 'qr',
                'fulfillment_status' => 'pending',
                'table_name' => $temporaryOrder->table?->name,
                'area_name' => $temporaryOrder->table?->area?->name,
                'total_amount' => (float) $temporaryOrder->total_amount,
                'items_count' => $items->count(),
                'items_prepared_count' => 0,
                'items_served_count' => 0,
                'items_in_progress_count' => 0,
                'items_ready_count' => 0,
                'created_at' => $temporaryOrder->created_at->format('H:i'),
                'created_at_full' => $temporaryOrder->created_at->format('H:i:s - d/m/Y'),
                'created_at_formatted' => $temporaryOrder->created_at->format('H:i - d/m/Y'),
                'completed_at' => null,
                'completed_at_full' => null,
                'completed_at_formatted' => null,
                'note' => $temporaryOrder->notes,
                'items' => $items->map(fn (array $item, int $index) => [
                    'id' => $item['product_id'] ?? $index,
                    'name' => $item['name'] ?? 'Món ăn',
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'unit_price' => (float) ($item['unit_price'] ?? 0),
                    'line_total' => (float) ($item['line_total'] ?? 0),
                    'notes' => $item['notes'] ?? null,
                    'status' => 'cancelled',
                ])->values()->all(),
                'delivery' => null,
                'cancelled_at' => $temporaryOrder->updated_at->format('H:i - d/m/Y'),
                'cancelled_by_name' => $temporaryOrder->cancelledBy?->name ?? 'Hệ thống',
                'cancellation_reason' => $temporaryOrder->cancellation_reason,
            ];
        })->values()->all();

        $cancelledQrCount = count($cancelledQrOrders);
        $summary['total'] += $cancelledQrCount;
        $summary['cancelled'] += $cancelledQrCount;

        $autoPaySetting = DB::table('restaurant_settings')
            ->where('restaurant_id', $restaurantId)
            ->where('key_name', 'auto_pay_on_last_shift_close')
            ->value('value');
        $autoPayEnabled = filter_var(json_decode($autoPaySetting ?? 'false'), FILTER_VALIDATE_BOOLEAN);

        return Inertia::render('orders/Index', [
            'orders' => $orders,
            'cancelledQrOrders' => $cancelledQrOrders,
            'summary' => $summary,
            'filters' => ['status' => $statusFilter, 'date' => $dateFilter],
            'autoPayEnabled' => $autoPayEnabled,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch((int) $order->branch_id), 403);

        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('manage_kitchen') || $user->can('create_orders'), 403);

        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,preparing,completed,cancelled'],
            'bypass_code' => ['nullable', 'string'],
        ]);

        if ($data['status'] === 'cancelled') {
            if (! $this->policy->isWithinShiftHours($user)) {
                return back()->withErrors(['status' => 'Bạn chỉ được hủy đơn trong giờ ca làm việc hiện tại theo chính sách nhà hàng.']);
            }

            $decision = $this->policy->canCancelOrder($user, (float) $order->total_amount);
            if (! $decision['allowed'] && ! ($decision['requires_approval'] ?? false)) {
                return back()->withErrors(['status' => $decision['message'] ?? 'Bạn không có quyền hủy đơn hàng này.']);
            }

            if (! $decision['allowed']) {
                $approvingUser = User::validateManagerBypass($data['bypass_code'] ?? '', $order->restaurant_id);
                if (! $approvingUser) {
                    return back()->withErrors(['status' => $decision['message'].' Vui lòng nhập mã phê duyệt của quản lý.']);
                }

                // Ghi log bypass hủy đơn vượt hạn mức chính sách.
                AuditLog::log('order_cancelled_bypass', 'updated', $order, ['status' => $order->status], [
                    'status' => 'cancelled',
                    'order_amount' => (float) $order->total_amount,
                    'max_allowed' => $decision['max_allowed'] ?? null,
                    'bypass_code_used' => true,
                    'approved_by_user_id' => $approvingUser->id,
                    'approved_by_user_name' => $approvingUser->name,
                ]);
            }
        }

        try {
            $this->orderService->updateOrderStatus($order, $data['status'], $user);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã cập nhật trạng thái đơn hàng.');
    }

    /**
     * Hủy một món từ POS. Món chưa bắt đầu được bếp xử lý ngay; món đã bắt
     * đầu sẽ tạo yêu cầu quản lý nếu người thao tác chưa có quyền duyệt.
     */
    public function cancelItem(
        Request $request,
        Order $order,
        OrderItem $item,
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        abort_if($order->restaurant_id !== $user->restaurant_id, 403);
        abort_if($item->restaurant_id !== $user->restaurant_id || $item->order_id !== $order->id, 404);
        $hasApprovalAuthority = $user->can('approve_requests') || $user->hasAnyRole(['owner', 'manager']);
        abort_unless($user->can('manage_orders')
            || $user->can('create_orders')
            || $user->can('process_payments')
            || $hasApprovalAuthority, 403);
        abort_unless($user->canAccessBranch((int) $order->branch_id), 403);

        // [SECURITY P1] Hủy món đã bắt đầu chế biến: chỉ Owner được hủy trực tiếp.
        // Manager phải gửi approval request — tránh tiêu hao nguyên liệu mà không có dấu vết kiểm toán.
        // $hasApprovalAuthority dùng để kiểm tra CÓ ĐƯỢC tạo approval hay không (tất cả đều được).
        // $canDirectCancel dùng để kiểm tra CÓ ĐƯỢC hủy mà không cần approval khi đã chế biến.
        $canDirectCancel = $user->hasAnyRole(['owner']) || $user->isSuperAdmin();

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $reason = trim($data['reason']);

        $freshItem = OrderItem::whereKey($item->id)->firstOrFail();
        $hasStarted = $freshItem->started_preparing_at !== null
            || $freshItem->prepared_at !== null
            || $freshItem->status === 'preparing';

        if ($hasStarted && ! $canDirectCancel) {
            $alreadyPending = ApprovalRequest::forRestaurant($order->restaurant_id)
                ->where('operation_type', 'order_item_cancel')
                ->where('status', 'pending')
                ->get()
                ->contains(fn (ApprovalRequest $approval): bool => (int) data_get(
                    $approval->operation_data,
                    'order_item_id',
                ) === (int) $item->id);

            if ($alreadyPending) {
                return back()->withErrors(['item' => 'Món này đã có yêu cầu hủy đang chờ Chủ nhà hàng duyệt.']);
            }

            $approval = app(ApprovalService::class)->submitRequest('order_item_cancel', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_item_id' => $item->id,
                'product_name' => $item->product?->name,
                'table_name' => $order->table?->name,
                'reason' => $reason,
                'was_started' => true,
            ], $user);

            AuditLog::log('order_item_cancel_requested', 'updated', $item, null, [
                'approval_id' => $approval->id,
                'order_id' => $order->id,
                'reason' => $reason,
                'requested_by_user_id' => $user->id,
                'requested_by_user_name' => $user->name,
            ]);

            return back()->with('success', 'Đã gửi yêu cầu hủy món đến Chủ nhà hàng phê duyệt (món đã bắt đầu chế biến).');
        }

        try {
            $result = app(OrderItemCancellationService::class)->cancel($item, $user, $reason);
        } catch (\Throwable $exception) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['item' => $exception->getMessage()]);
        }

        $message = "Đã hủy món {$result['product_name']} và báo xuống bếp.";
        if ((float) $result['refund_amount'] > 0) {
            $message .= ' Đã ghi nhận hoàn '.number_format($result['refund_amount'], 0, ',', '.').'đ.';
        }

        return back()->with('success', $message);
    }

    /**
     * Tách đơn hàng hiện tại sang một bàn trống mới (Cashier / Staff).
     */
    public function split(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch((int) $order->branch_id), 403);

        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('split_orders'), 403);

        $data = $request->validate([
            'table_id' => ['required', TenantRule::exists('restaurant_tables')],
            'items' => ['required', 'array'],
            'items.*.order_item_id' => ['required', TenantRule::exists('order_items')],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $this->orderService->splitOrder($order, $data, $user);

        return back()->with('success', 'Đã tách đơn hàng ra bàn trống thành công. Giao dịch này đã được đánh dấu đỏ cảnh báo.');
    }

    /**
     * Phê duyệt đối soát đơn bị tách để xóa khoản phạt âm tiền (Chỉ Owner).
     */
    public function overrideSplitPenalty(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('override_split_penalty'), 403);
        abort_if($order->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $order->branch_id), 403);
        abort_unless((bool) $order->is_split, 422);

        $this->orderService->overrideSplitPenalty($order, $user);

        return back()->with('success', 'Đã phê duyệt đối soát đơn tách. Khoản phạt âm tiền của ca làm việc đã được vô hiệu hóa.');
    }

    /**
     * Sửa đổi thông tin đơn hàng / giá món / áp dụng giảm giá (Cashier / Manager).
     */
    public function update(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch((int) $order->branch_id), 403);

        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('create_orders'), 403);

        $data = $request->validate([
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', TenantRule::exists('order_items')],
            'items.*.product_id' => ['nullable', TenantRule::exists('products')],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'guests_count' => ['nullable', 'integer', 'min:1'],
            'bypass_code' => ['nullable', 'string'],
        ]);

        if (array_key_exists('discount_amount', $data)) {
            if (! $this->policy->isWithinShiftHours($user)) {
                return back()->withErrors(['discount_amount' => 'Bạn chỉ được áp dụng giảm giá trong giờ ca làm việc hiện tại theo chính sách nhà hàng.']);
            }

            $subtotal = max(0.0, (float) $order->subtotal);
            $discountAmount = (float) $data['discount_amount'];
            $discountPercent = $subtotal > 0 ? ($discountAmount / $subtotal) * 100 : 0;
            $decision = $this->policy->canApplyDiscount($user, $discountPercent);

            if (! $decision['allowed'] && ! ($decision['requires_approval'] ?? false)) {
                return back()->withErrors(['discount_amount' => $decision['message'] ?? 'Bạn không có quyền áp dụng mức giảm giá này.']);
            }

            if (! $decision['allowed'] && ($decision['requires_approval'] ?? false)) {
                $approvingUser = User::validateManagerBypass($data['bypass_code'] ?? '', $order->restaurant_id);
                if (! $approvingUser) {
                    return back()->withErrors(['discount_amount' => $decision['message'].' Vui lòng nhập mã phê duyệt của quản lý.']);
                }

                AuditLog::log('discount_applied_bypass', 'updated', $order, [
                    'discount_amount' => (float) $order->discount_amount,
                ], [
                    'discount_amount' => $discountAmount,
                    'discount_percent' => round($discountPercent, 2),
                    'max_allowed_percent' => $decision['max_allowed'] ?? null,
                    'bypass_code_used' => true,
                    'approved_by_user_id' => $approvingUser->id,
                    'approved_by_user_name' => $approvingUser->name,
                ]);
            }
        }

        if (isset($data['guests_count']) && $order->table_id) {
            $table = RestaurantTable::where('restaurant_id', $order->restaurant_id)
                ->where('branch_id', $order->branch_id)
                ->find($order->table_id);
            if ($table && (int) $data['guests_count'] > (int) $table->capacity) {
                return back()->withErrors(['guests_count' => "Số lượng khách ({$data['guests_count']}) vượt quá sức chứa tối đa của bàn {$table->name} (Tối đa {$table->capacity} chỗ). Vui lòng chọn ghép bàn hoặc chuyển bàn lớn hơn."]);
            }
        }

        if (isset($data['items'])) {
            $productIds = collect($data['items'])->pluck('product_id')->filter()->unique()->toArray();
            $products = Product::where('restaurant_id', $order->restaurant_id)
                ->where(function ($query) use ($order) {
                    $query->whereNull('branch_id')->orWhere('branch_id', $order->branch_id);
                })
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            foreach ($data['items'] as $itemData) {
                if (! empty($itemData['product_id'])) {
                    $prod = $products->get($itemData['product_id']);
                    if ($prod && isset($itemData['unit_price']) && (float) $itemData['unit_price'] !== (float) $prod->price
                        && ! $user->isOwner() && ! $user->isSuperAdmin()) {
                        $approvingUser = User::validateManagerBypass($data['bypass_code'] ?? '', $order->restaurant_id);
                        if (! $approvingUser || (! $approvingUser->isOwner() && ! $approvingUser->isSuperAdmin())) {
                            return back()->withErrors(['items' => 'Thay đổi đơn giá món trực tiếp yêu cầu mã phê duyệt của Chủ doanh nghiệp.']);
                        }
                        // Ghi log bypass thay đổi đơn giá món
                        AuditLog::log('price_discount_bypass', 'updated', $order, null, [
                            'product_id' => $prod->id,
                            'original_price' => $prod->price,
                            'new_price' => $itemData['unit_price'],
                            'bypass_code_used' => true,
                            'approved_by_user_id' => $approvingUser->id,
                            'approved_by_user_name' => $approvingUser->name,
                        ]);
                    }
                }
            }
        }

        // Order Locking: Allow deleting or decreasing quantity of items if they are still 'pending' or 'sent'.
        // If they are 'preparing', 'ready', or 'served', require a manager bypass.
        if (isset($data['items'])) {
            $payloadItemIds = collect($data['items'])->pluck('id')->filter()->toArray();
            $dbItems = $order->items()->where('status', '!=', 'cancelled')->get();

            $needsBypass = false;
            $bypassReasons = [];

            foreach ($dbItems as $dbItem) {
                $isDeleted = ! in_array($dbItem->id, $payloadItemIds);
                $payloadItem = collect($data['items'])->firstWhere('id', $dbItem->id);
                $isDecreased = $payloadItem && (float) $payloadItem['quantity'] < (float) $dbItem->quantity;

                if ($isDeleted || $isDecreased) {
                    // Check if the item is already being cooked or served
                    if (in_array($dbItem->status, ['preparing', 'ready', 'served'])) {
                        $needsBypass = true;
                        $bypassReasons[] = ($isDeleted ? 'Xóa món' : 'Giảm số lượng')." {$dbItem->product->name} (Trạng thái: {$dbItem->status})";
                    }
                }
            }

            if ($needsBypass) {
                $approvingUser = User::validateManagerBypass($data['bypass_code'] ?? '', $order->restaurant_id);
                if (! $approvingUser) {
                    return back()->withErrors(['items' => 'Thay đổi hoặc xóa món ăn đã chế biến yêu cầu mã phê duyệt của quản lý hoặc chưa cấu hình mã phê duyệt. Chi tiết: '.implode(', ', $bypassReasons)]);
                }

                // Log the bypass action
                AuditLog::log('order_item_lock_bypass', 'updated', $order, null, [
                    'reasons' => $bypassReasons,
                    'bypass_code_used' => true,
                    'approved_by_user_id' => $approvingUser->id,
                    'approved_by_user_name' => $approvingUser->name,
                ]);
            }
        }

        $this->orderService->updateOrder($order, $data, $user);

        return back()->with('success', 'Đã cập nhật thông tin đơn hàng và ghi nhận nhật ký kiểm toán.');
    }

    /**
     * Xác nhận đơn hàng QR từ khách hàng và lấy gợi ý upselling AI.
     */
    public function confirmQr(Request $request, Order $order): JsonResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch((int) $order->branch_id), 403);
        abort_if($order->status !== 'pending', 422, 'Đơn hàng này đã được xác nhận trước đó.');

        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('create_orders'), 403);

        $this->orderService->confirmQr($order, $user);

        // Lấy gợi ý Upselling từ PromotionController
        $promotionController = app(PromotionController::class);
        $itemNames = $order->items->map(fn ($item) => $item->product?->name)->filter()->toArray();
        $upsellRequest = new Request(['items' => $itemNames]);
        $upsellRequest->setUserResolver(fn () => $user);
        $suggestionResult = $promotionController->getUpsellSuggestion($upsellRequest);
        $upsell = $suggestionResult->getData();

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận đơn hàng QR thành công!',
            'upsell' => $upsell,
        ]);
    }

    /**
     * Thanh toán đơn hàng (Chỉ cashier/owner).
     */
    public function pay(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('process_payments'), 403);
        abort_if($order->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $order->branch_id), 403);
        abort_if($order->payment_status === 'paid', 422, 'Đơn hàng này đã được thanh toán rồi.');

        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,bank_transfer,card,ewallet,debt,multi'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'change_amount' => ['nullable', 'numeric', 'min:0'],
            'redeem_points' => ['nullable', 'integer', 'min:0'],
            'customer_id' => ['nullable', TenantRule::exists('customers')],
            'payments' => ['nullable', 'array', 'min:1'],
            'payments.*.payment_method' => ['required_with:payments', 'string', 'in:cash,bank_transfer,card,ewallet,vietqr'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.cash_received' => ['nullable', 'numeric', 'min:0'],
            'payments.*.change_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($data['payment_method'] === 'debt') {
            $customerId = $order->customer_id ?: ($data['customer_id'] ?? null);
            if (! $customerId) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Giao dịch ghi nợ yêu cầu thông tin khách hàng.'], 422);
                }

                return back()->withErrors(['customer_id' => 'Giao dịch ghi nợ yêu cầu thông tin khách hàng.']);
            }

            try {
                DB::transaction(function () use ($order, $customerId, $data, $user) {
                    if ($order->table_id) {
                        RestaurantTable::where('id', $order->table_id)
                            ->where('restaurant_id', $order->restaurant_id)
                            ->where('branch_id', $order->branch_id)
                            ->lockForUpdate()
                            ->first();
                    }

                    // Lock order row tr\u01b0\u1edbc \u2014 ng\u0103n concurrent payment v\u00e0o c\u00f9ng \u0111\u01a1n
                    $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

                    // Idempotency guard: n\u1ebfu \u0111\u01a1n \u0111\u00e3 \u0111\u01b0\u1ee3c thanh to\u00e1n r\u1ed3i, kh\u00f4ng x\u1eed l\u00fd l\u1ea1i
                    if ($order->status === 'completed' || $order->payment_status !== 'unpaid') {
                        throw new \Exception('Đơn hàng này đã được xử lý thanh toán trước đó.');
                    }

                    $this->orderService->assertCanBePaid($order);

                    // Lock the customer row
                    $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();

                    if (! $customer->is_vip && ! $customer->is_b2b) {
                        throw new \Exception('Khách hàng không được cấp quyền ghi nợ (Yêu cầu VIP/B2B).');
                    }

                    $newDebt = (float) $customer->current_debt + (float) $order->total_amount;
                    if ($newDebt > (float) $customer->credit_limit) {
                        throw new \Exception('Hạn mức tín dụng của khách hàng không đủ.');
                    }

                    // Cập nhật customer_id an toàn trong transaction
                    if (isset($data['customer_id']) && $data['customer_id']) {
                        $order->update(['customer_id' => $data['customer_id']]);
                    }

                    // Apply membership discount
                    if (! str_contains($order->note ?? '', '[Ưu đãi Hội viên')) {
                        $lvl = $customer->membership_level ?? 'silver';
                        $loyaltyDiscount = 0.0;
                        if ($lvl === 'diamond') {
                            $loyaltyDiscount = round($order->subtotal * 0.10, 2);
                        } elseif ($lvl === 'gold') {
                            $loyaltyDiscount = round($order->subtotal * 0.05, 2);
                        }

                        if ($loyaltyDiscount > 0) {
                            $order->discount_amount = (float) $order->discount_amount + $loyaltyDiscount;
                            $order->total_amount = max(0.0, (float) $order->subtotal - (float) $order->discount_amount);
                            $order->note = ($order->note ? $order->note.' ' : '').'[Ưu đãi Hội viên '.($lvl === 'diamond' ? 'Kim Cương' : 'Vàng').': -'.number_format($loyaltyDiscount).'đ]';
                            $order->save();
                        }
                    }

                    // Increment customer debt
                    $customer->increment('current_debt', $order->total_amount);

                    // Create AccountReceivable record
                    AccountReceivable::create([
                        'restaurant_id' => $order->restaurant_id,
                        'branch_id' => $order->branch_id,
                        'order_id' => $order->id,
                        'customer_id' => $customer->id,
                        'amount' => $order->total_amount,
                        'received_amount' => 0,
                        'due_date' => now()->addDays(30)->toDateString(), // 30-day payment term
                        'status' => 'unpaid',
                    ]);

                    // Ghi nhận doanh thu và phải thu cho đơn bán ghi nợ
                    if ((float) $order->total_amount > 0) {
                        app(FinancialPostingService::class)->post([
                            'restaurant_id' => $order->restaurant_id,
                            'branch_id' => $order->branch_id,
                            'entry_date' => now()->toDateString(),
                            'source_type' => 'order',
                            'source_id' => $order->id,
                            'idempotency_key' => "order_credit_sale_{$order->id}",
                            'description' => "Doanh thu bán ghi nợ đơn hàng #{$order->order_number}",
                            'lines' => [
                                ['account' => '1311', 'debit' => (float) $order->total_amount, 'credit' => 0],
                                ['account' => '5111', 'debit' => 0, 'credit' => (float) $order->total_amount],
                            ],
                        ]);
                    }

                    // Create Payment record
                    Payment::create([
                        'restaurant_id' => $order->restaurant_id,
                        'branch_id' => $order->branch_id,
                        'order_id' => $order->id,
                        'processed_by' => $user->id,
                        'payment_method' => 'debt',
                        'status' => 'unpaid',
                        'amount' => $order->total_amount,
                        'cash_received' => 0,
                        'change_amount' => 0,
                        'paid_at' => null,
                    ]);

                    // Deduct inventory (an toàn vì đã có idempotency guard ở trên)
                    app(InventoryService::class)->deductInventoryForOrder($order, $user);

                    // Update order to completed and payment_status to unpaid
                    $order->update([
                        'status' => 'completed',
                        'payment_status' => 'unpaid',
                        'completed_at' => now(),
                        'cashier_user_id' => $user->id,
                    ]);

                    // Chỉ giải phóng bàn khi không còn đơn/món nào đang phục vụ.
                    if ($order->table_id) {
                        $table = RestaurantTable::where('id', $order->table_id)
                            ->where('restaurant_id', $order->restaurant_id)
                            ->where('branch_id', $order->branch_id)
                            ->lockForUpdate()
                            ->first();

                        if ($table && ! $table->orders()->activeForService()->exists()) {
                            $table->update(['status' => 'available']);
                        }
                    }

                    // Customer updates + loyalty points
                    $customer->update(['last_order_at' => now()]);
                    $loyaltyService = app(LoyaltyService::class);
                    $loyaltyService->earnPoints($customer, $order, (float) $order->total_amount);
                    $loyaltyService->recalculateTier($customer);
                    CdpService::calculateRfmForCustomer($customer, $order->branch_id);

                    AuditLog::log('order_paid_with_debt', 'updated', $order, ['payment_status' => 'unpaid'], ['payment_status' => 'unpaid']);
                });
            } catch (\Exception $e) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => $e->getMessage()], 422);
                }

                return back()->withErrors(['customer_id' => $e->getMessage()]);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ghi nợ đơn hàng thành công!',
                ]);
            }

            return back()->with('success', 'Ghi nợ đơn hàng thành công!');
        }

        // P1: Luôn queue các tác vụ nặng sau thanh toán (inventory deduction,
        // loyalty points, RFM recalculation) để response về POS ngay lập tức.
        try {
            // Khấu trừ BOM phải nằm trong cùng transaction với payment. Nếu
            // queue sau khi ghi paid, job có thể thất bại vì hết kho và để lại
            // doanh thu không có tồn/COGS tương ứng.
            $this->orderService->payOrder($order, $data, $user, queuePostPayment: true);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return back()->withErrors(['items' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Thanh toán đơn hàng thành công!',
            ]);
        }

        return back()->with('success', 'Thanh toán đơn hàng thành công!');
    }

    public function toggleAutoPaySetting(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('approve_requests') || $user->can('manage_restaurant_settings'), 403);

        $restaurantId = $user->restaurant_id;

        $setting = DB::table('restaurant_settings')
            ->where('restaurant_id', $restaurantId)
            ->where('key_name', 'auto_pay_on_last_shift_close')
            ->first();

        if ($setting) {
            $currentVal = filter_var(json_decode($setting->value) ?? $setting->value, FILTER_VALIDATE_BOOLEAN);
            $newVal = ! $currentVal;
            DB::table('restaurant_settings')
                ->where('id', $setting->id)
                ->update(['value' => json_encode($newVal), 'updated_at' => now()]);
        } else {
            $newVal = true;
            DB::table('restaurant_settings')->insert([
                'restaurant_id' => $restaurantId,
                'key_name' => 'auto_pay_on_last_shift_close',
                'value' => json_encode($newVal),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Đã cập nhật chế độ tự động thanh toán đơn ca cuối.');
    }

    /**
     * Tạo yêu cầu hoàn tiền cho nhân viên; chủ doanh nghiệp xử lý trực tiếp.
     */
    public function refund(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('process_payments') || $user->can('approve_requests'), 403);
        abort_if($order->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $order->branch_id), 403);
        abort_unless(in_array($order->payment_status, ['paid', 'partial_refund'], true), 422, 'Chỉ có thể hoàn tiền đơn đã thanh toán.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            // Keep older POS clients compatible; new clients may explicitly
            // choose whether the refund is compensation or an input mistake.
            'refund_category' => ['nullable', 'in:compensation,mistake'],
            'refund_amount' => ['required', 'numeric', 'min:1000', "max:{$order->total_amount}"],
            'refund_type' => ['required', 'in:full,partial'],
            'items' => ['nullable', 'array'],
            'items.*.order_item_id' => ['required_with:items', 'integer'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:1'],
        ]);

        // Historical refund requests predate the category field. Treat them
        // as compensation, which is the conservative inventory-accounting
        // default, instead of rejecting an otherwise valid refund.
        $data['refund_category'] ??= 'compensation';

        $alreadyRefunded = (float) ($order->refund_amount ?? 0);
        $remainingRefundable = max(0.0, (float) $order->total_amount - $alreadyRefunded);
        if ((float) $data['refund_amount'] > $remainingRefundable + 0.01) {
            return back()->withErrors(['refund_amount' => 'Số tiền hoàn vượt quá số tiền còn được hoàn của đơn hàng.']);
        }
        if ($data['refund_type'] === 'full' && abs((float) $data['refund_amount'] - $remainingRefundable) > 0.01) {
            return back()->withErrors(['refund_amount' => 'Hoàn toàn phần phải bằng đúng số tiền còn được hoàn.']);
        }

        // Chủ doanh nghiệp có thể xử lý trực tiếp; không cần mã bypass.
        if ($user->hasRole('owner')) {
            app(OrderRefundService::class)->process($order, $data, $user);

            return back()->with('success', 'Đã xử lý hoàn tiền thành công. Nhật ký kiểm toán đã được ghi nhận.');
        }

        $hasPendingRequest = ApprovalRequest::forRestaurant($order->restaurant_id)
            ->where('operation_type', 'order_refund')
            ->where('status', 'pending')
            ->get()
            ->contains(fn (ApprovalRequest $approval) => (int) data_get($approval->operation_data, 'order_id') === (int) $order->id);

        if ($hasPendingRequest) {
            return back()->withErrors(['refund' => 'Đơn hàng này đã có yêu cầu hoàn tiền đang chờ chủ doanh nghiệp phê duyệt.']);
        }

        $categoryLabel = $data['refund_category'] === 'mistake' ? 'Nhầm lẫn (Không trừ tồn)' : 'Bồi thường (Trừ tồn)';

        $approval = app(ApprovalService::class)->submitRequest('order_refund', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'table_name' => $order->table?->name,
            'refund_type' => $data['refund_type'],
            'refund_category' => $data['refund_category'],
            'refund_amount' => (float) $data['refund_amount'],
            'refund_reason' => "[Phân loại: {$categoryLabel}] {$data['reason']}",
            'reason' => "[Phân loại: {$categoryLabel}] {$data['reason']}",
            'items' => $data['items'] ?? [],
        ], $user);

        AuditLog::log('refund_requested', 'updated', $order, null, [
            'approval_id' => $approval->id,
            'refund_amount' => (float) $data['refund_amount'],
            'refund_type' => $data['refund_type'],
            'refund_category' => $data['refund_category'],
            'refund_reason' => $data['reason'],
            'requested_by_user_id' => $user->id,
            'requested_by_user_name' => $user->name,
            'is_sensitive' => true,
            'alert_level' => 'warning',
            'warning_message' => "Quản lý {$user->name} vừa gửi yêu cầu hoàn tiền nhạy cảm ({$categoryLabel}) cho đơn #{$order->order_number} số tiền ".number_format((float) $data['refund_amount'], 0, ',', '.')." ₫.",
        ]);

        return back()->with('success', 'Đã gửi yêu cầu hoàn tiền đến chủ doanh nghiệp để phê duyệt.');
    }

    public function batchApproveQrOrders(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->hasAnyRole(['cashier', 'manager', 'owner']), 403);

        $restaurantId = $user->restaurant_id;
        $branchId = app(TenantContext::class)->activeBranchId();

        $ids = $request->input('temporary_order_ids');

        $query = TemporaryOrder::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['waiting_verification', 'pending', 'escalated'])
            ->where('awaiting_customer_confirmation', false)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! empty($ids) && is_array($ids), fn ($q) => $q->whereIn('id', $ids));

        $tempOrders = $query->get();

        if ($tempOrders->isEmpty()) {
            $msg = 'Không có đơn hàng QR nào đang chờ duyệt.';

            return $request->wantsJson() ? response()->json(['success' => false, 'message' => $msg], 422) : back()->withErrors(['qr' => $msg]);
        }

        $approvedCount = 0;
        $overdueOrders = [];

        foreach ($tempOrders as $tempOrder) {
            $createdAt = $tempOrder->created_at ? Carbon::parse($tempOrder->created_at) : now();
            $minutesAgo = $createdAt->diffInMinutes(now());

            if ($minutesAgo > 30) {
                $overdueOrders[] = [
                    'id' => $tempOrder->id,
                    'order_number' => 'QR-TEMP-'.$tempOrder->id,
                    'created_at' => $createdAt->format('H:i - d/m/Y'),
                    'minutes_ago' => $minutesAgo,
                ];

                continue;
            }

            try {
                DB::transaction(function () use ($tempOrder, $user) {
                    $lockedTempOrder = TemporaryOrder::where('id', $tempOrder->id)->lockForUpdate()->firstOrFail();

                    if (! in_array($lockedTempOrder->status, ['waiting_verification', 'pending', 'escalated'], true)
                        || $lockedTempOrder->awaiting_customer_confirmation) {
                        return;
                    }

                    $customerId = null;
                    $customer = null;
                    if ($lockedTempOrder->customer_phone) {
                        $customer = Customer::firstOrCreate(
                            [
                                'restaurant_id' => $lockedTempOrder->restaurant_id,
                                'phone' => $lockedTempOrder->customer_phone,
                            ],
                            [
                                'full_name' => $lockedTempOrder->customer_name ?: 'Khách gọi món QR',
                                'branch_id' => $lockedTempOrder->branch_id,
                            ]
                        );
                        $customerId = $customer->id;
                        $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();
                    }

                    if ($lockedTempOrder->redeem_points > 0 && (! $customer || $customer->loyalty_points < $lockedTempOrder->redeem_points)) {
                        throw new \Exception('Khách hàng không đủ điểm tích lũy để thực hiện quy đổi.');
                    }

                    $orderData = [
                        'table_id' => $lockedTempOrder->table_id,
                        'customer_id' => $customerId,
                        'channel' => 'qr',
                        'note' => "Đơn QR-Order [Duyệt hàng loạt bởi: {$user->name}]",
                        'items' => collect($lockedTempOrder->cart_data)->map(fn ($item) => [
                            'product_id' => $item['product_id'],
                            'quantity' => (float) $item['quantity'],
                            'notes' => $item['notes'] ?? null,
                        ])->toArray(),
                    ];

                    try {
                        $order = $this->orderService->createOrder($orderData, $user, false);
                    } catch (\Throwable $e) {
                        $order = Order::create([
                            'restaurant_id' => $lockedTempOrder->restaurant_id,
                            'branch_id' => $lockedTempOrder->branch_id,
                            'table_id' => $lockedTempOrder->table_id,
                            'customer_id' => $customerId,
                            'order_number' => 'ORD-'.Str::ulid(),
                            'status' => 'preparing',
                            'payment_status' => 'unpaid',
                            'channel' => 'qr',
                            'fulfillment_status' => 'preparing',
                            'total_amount' => $lockedTempOrder->total_amount,
                            'subtotal' => $lockedTempOrder->total_amount,
                            'note' => "Đơn QR-Order [Duyệt hàng loạt bởi: {$user->name}]",
                            'created_at' => now(),
                        ]);

                        foreach (($lockedTempOrder->cart_data ?? []) as $item) {
                            OrderItem::create([
                                'restaurant_id' => $lockedTempOrder->restaurant_id,
                                'order_id' => $order->id,
                                'product_id' => $item['product_id'] ?? null,
                                'quantity' => (float) ($item['quantity'] ?? 1),
                                'unit_price' => (float) ($item['unit_price'] ?? 0),
                                'line_total' => (float) ($item['line_total'] ?? 0),
                                'notes' => $item['notes'] ?? null,
                                'sent_to_kitchen_at' => now(),
                            ]);
                        }
                    }
                    $order->update(['channel' => 'qr']);

                    if ($order->table_id) {
                        RestaurantTable::whereKey($order->table_id)->update(['status' => 'occupied']);
                    }

                    if ($lockedTempOrder->redeem_points > 0 && $customer) {
                        $discountValue = app(LoyaltyService::class)->redeemPoints($customer, $lockedTempOrder->redeem_points, $order);
                        if ($discountValue > 0) {
                            $newDiscount = $order->discount_amount + $discountValue;
                            $newTotal = max(0.0, $order->subtotal - $newDiscount);
                            $order->update([
                                'discount_amount' => $newDiscount,
                                'total_amount' => $newTotal,
                                'note' => ($order->note ? $order->note.' ' : '')."[Quy đổi {$lockedTempOrder->redeem_points} điểm: -".number_format($discountValue).'đ]',
                            ]);
                        }
                    }

                    $lockedTempOrder->update([
                        'status' => 'approved',
                        'order_id' => $order->id,
                    ]);

                    AuditLog::log(
                        'qr_order_batch_approved',
                        'created',
                        $order,
                        null,
                        ['temporary_order_id' => $lockedTempOrder->id]
                    );

                    try {
                        event(new TemporaryOrderUpdated($lockedTempOrder->fresh()));
                    } catch (\Throwable $e) {
                        Log::warning('Broadcast batch temporary order updated failed: '.$e->getMessage());
                    }
                });

                $approvedCount++;
            } catch (\Throwable $e) {
                Log::error("batchApproveQrOrders failed for temp order #{$tempOrder->id}: ".$e->getMessage());
            }
        }

        $message = "Đã duyệt hàng loạt {$approvedCount} đơn QR và đẩy xuống Bếp thành công.";
        if (! empty($overdueOrders)) {
            $message .= ' Có '.count($overdueOrders).' đơn chờ quá 30 phút cần xử lý riêng.';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'approved_count' => $approvedCount,
                'overdue_orders' => $overdueOrders,
            ]);
        }

        return back()->with('success', $message);
    }
}
