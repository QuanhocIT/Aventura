<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Services\Integrations\WebhookDispatchService;
use App\Services\PaymentGatewayService;
use App\Support\Tenant\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TableReservationController extends Controller
{
    /**
     * Danh sách đặt bàn — dành cho Manager/Owner.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('approve_requests'), 403);

        $rid = $user->restaurant_id;
        $branchId = app(TenantContext::class)->activeBranchId();

        $dateFilter = $request->get('date', today()->toDateString());
        $statusFilter = $request->get('status', 'all');

        $query = TableReservation::with(['table', 'customer', 'confirmedBy'])
            ->where('restaurant_id', $rid)
            ->where('reservation_date', $dateFilter)
            ->when($branchId !== null, fn ($query) => $query->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }));

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $reservations = $query->orderBy('reservation_time')->get()->map(fn ($r) => [
            'id' => $r->id,
            'guest_name' => $r->guest_name,
            'guest_phone' => $r->guest_phone,
            'guest_email' => $r->guest_email,
            'reservation_date' => $r->reservation_date->format('d/m/Y'),
            'reservation_time' => $r->reservation_time,
            'party_size' => $r->party_size,
            'status' => $r->status,
            'status_label' => $r->status_label,
            'status_color' => $r->status_color,
            'special_requests' => $r->special_requests,
            'internal_notes' => $r->internal_notes,
            'table_name' => $r->table?->name,
            'confirmed_by_name' => $r->confirmedBy?->name,
            'source' => $r->source,
            'deposit_amount' => (float) ($r->deposit_amount ?? 0),
            'deposit_status' => $r->deposit_status ?? 'none',
        ]);

        // Thống kê hôm nay
        $todayStats = TableReservation::where('restaurant_id', $rid)
            ->where('reservation_date', today())
            ->when($branchId !== null, fn ($query) => $query->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $availableTables = RestaurantTable::where('restaurant_id', $rid)
            ->where('status', 'available')
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->get(['id', 'name', 'capacity']);

        return Inertia::render('reservations/Index', [
            'reservations' => $reservations,
            'todayStats' => $todayStats,
            'availableTables' => $availableTables,
            'filters' => ['date' => $dateFilter, 'status' => $statusFilter],
        ]);
    }

    /**
     * Xác nhận đặt bàn và assign bàn cụ thể.
     */
    public function confirm(Request $request, TableReservation $reservation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('approve_requests'), 403);
        abort_if($reservation->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'table_id' => ['nullable', "exists:restaurant_tables,id,restaurant_id,{$user->restaurant_id}"],
            'internal_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($reservation, $data, $user) {
                $lockedReservation = TableReservation::where('id', $reservation->id)
                    ->where('restaurant_id', $user->restaurant_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($lockedReservation->status !== 'pending') {
                    throw new \Exception('Chỉ xác nhận được đặt bàn đang chờ.');
                }

                $assignedTableBranchId = $lockedReservation->branch_id;

                if (! empty($data['table_id'])) {
                    // Kiểm tra bàn có bị chiếm không
                    $table = RestaurantTable::where('id', $data['table_id'])->lockForUpdate()->firstOrFail();
                    if ($table->restaurant_id !== $user->restaurant_id || $table->status !== 'available') {
                        throw new \Exception('Bàn này không còn ở trạng thái trống để giữ chỗ.');
                    }

                    if ($lockedReservation->branch_id !== null && $table->branch_id !== $lockedReservation->branch_id) {
                        throw new \Exception('Bàn phải thuộc cùng chi nhánh với đặt bàn.');
                    }

                    $assignedTableBranchId = $table->branch_id ?? $assignedTableBranchId;

                    // Kiểm tra bàn có bị trùng trong khoảng ±90 phút không
                    // (không chỉ check chính xác cùng giờ — bàn cần ~90 phút phục vụ)
                    $reservationDateTime = Carbon::parse(
                        $lockedReservation->reservation_date->toDateString().' '.$lockedReservation->reservation_time
                    );
                    $windowStart = $reservationDateTime->copy()->subMinutes(90)->format('H:i:s');
                    $windowEnd = $reservationDateTime->copy()->addMinutes(90)->format('H:i:s');

                    $conflict = TableReservation::where('restaurant_id', $user->restaurant_id)
                        ->where('table_id', $data['table_id'])
                        ->when($assignedTableBranchId !== null, fn ($query) => $query->where(function ($q) use ($assignedTableBranchId) {
                            $q->whereNull('branch_id')->orWhere('branch_id', $assignedTableBranchId);
                        }))
                        ->where('reservation_date', $lockedReservation->reservation_date)
                        ->whereBetween('reservation_time', [$windowStart, $windowEnd])
                        ->whereIn('status', ['confirmed', 'seated'])
                        ->where('id', '!=', $lockedReservation->id)
                        ->exists();

                    if ($conflict) {
                        throw new \Exception('Bàn này đã được gán cho một đặt bàn khác cùng khung giờ.');
                    }
                }

                $lockedReservation->update([
                    'status' => 'confirmed',
                    'table_id' => $data['table_id'] ?? null,
                    'branch_id' => $assignedTableBranchId,
                    'internal_notes' => $data['internal_notes'] ?? $lockedReservation->internal_notes,
                    'confirmed_by' => $user->id,
                    'confirmed_at' => now(),
                ]);

                if (! empty($data['table_id'])) {
                    RestaurantTable::whereKey($data['table_id'])->update(['status' => 'reserved']);
                }

                // TRƯỚC ĐÂY dùng cột auditable_type/auditable_id (không tồn tại —
                // bảng dùng subject_type/subject_id) và thiếu cột 'event' NOT NULL,
                // khiến INSERT ném lỗi SQL, bị catch nuốt và ROLLBACK cả transaction:
                // xác nhận đặt bàn thực tế KHÔNG BAO GIỜ lưu được.
                AuditLog::log(
                    'reservation_confirmed',
                    'updated',
                    $lockedReservation,
                    ['status' => 'pending'],
                    ['status' => 'confirmed', 'table_id' => $data['table_id'] ?? null],
                );
            });
        } catch (\Exception $e) {
            return back()->withErrors(['table_id' => $e->getMessage()]);
        }

        return back()->with('success', "Đã xác nhận đặt bàn cho khách {$reservation->guest_name}.");
    }

    /**
     * Đánh dấu khách đã đến và được dẫn vào bàn.
     */
    public function seat(Request $request, TableReservation $reservation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('create_orders'), 403);
        abort_if($reservation->restaurant_id !== $user->restaurant_id, 403);

        try {
            DB::transaction(function () use ($reservation) {
                $lockedReservation = TableReservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail();
                if ($lockedReservation->status !== 'confirmed') {
                    throw new \Exception('Chỉ có thể dẫn khách vào bàn khi đặt đã được xác nhận.');
                }

                $lockedReservation->update([
                    'status' => 'seated',
                    'seated_at' => now(),
                ]);

                // Cập nhật trạng thái bàn thành 'occupied' nếu có assign bàn
                if ($lockedReservation->table_id) {
                    RestaurantTable::where('id', $lockedReservation->table_id)
                        ->where('restaurant_id', $lockedReservation->restaurant_id)
                        ->update(['status' => 'occupied']);
                }
            });
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "Đã dẫn khách {$reservation->guest_name} vào bàn.");
    }

    public function autoAssignTable(Request $request, TableReservation $reservation): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('approve_requests'), 403);

        $restaurantId = $user->restaurant_id;
        if ($reservation->restaurant_id !== $restaurantId) {
            abort(403);
        }

        $branchId = app(TenantContext::class)->activeBranchId() ?? $reservation->branch_id;

        $table = RestaurantTable::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'available')
            ->where('capacity', '>=', $reservation->party_size)
            ->orderBy('capacity', 'asc')
            ->first();

        if (! $table) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Không tìm thấy bàn trống phù hợp cho {$reservation->party_size} khách tại chi nhánh.",
                ], 422);
            }

            return back()->withErrors(['table_id' => "Không tìm thấy bàn trống phù hợp cho {$reservation->party_size} khách."]);
        }

        $reservation->update([
            'table_id' => $table->id,
            'status' => 'confirmed',
            'confirmed_by' => $user->id,
        ]);

        AuditLog::log(
            'reservation_auto_assigned',
            'updated',
            $reservation,
            null,
            ['table_id' => $table->id, 'table_name' => $table->name]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Đã tự động xếp bàn [{$table->name}] cho khách {$reservation->guest_name}!",
                'table_name' => $table->name,
                'reservation' => $reservation,
            ]);
        }

        return back()->with('success', "Đã tự động xếp bàn [{$table->name}] cho khách {$reservation->guest_name}!");
    }

    /**
     * Hủy đặt bàn.
     */
    public function cancel(Request $request, TableReservation $reservation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('approve_requests'), 403);
        abort_if($reservation->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        try {
            DB::transaction(function () use ($reservation, $data) {
                $lockedReservation = TableReservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail();
                if (! in_array($lockedReservation->status, ['pending', 'confirmed'])) {
                    throw new \Exception('Không thể hủy đặt bàn này.');
                }

                $lockedReservation->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $data['reason'],
                    'cancelled_at' => now(),
                ]);

                if ($lockedReservation->table_id) {
                    RestaurantTable::whereKey($lockedReservation->table_id)
                        ->where('status', 'reserved')
                        ->update(['status' => 'available']);
                }
            });
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "Đã hủy đặt bàn của khách {$reservation->guest_name}.");
    }

    /**
     * Đánh dấu khách không đến (no-show).
     */
    public function noShow(Request $request, TableReservation $reservation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('approve_requests'), 403);
        abort_if($reservation->restaurant_id !== $user->restaurant_id, 403);

        try {
            DB::transaction(function () use ($reservation) {
                $lockedReservation = TableReservation::where('id', $reservation->id)->lockForUpdate()->firstOrFail();
                if ($lockedReservation->status !== 'confirmed') {
                    throw new \Exception('Chỉ đánh dấu no-show cho đặt bàn đã xác nhận.');
                }

                $lockedReservation->update([
                    'status' => 'no_show',
                    'cancelled_at' => now(),
                ]);

                // Giải phóng bàn đã assign: khách không đến → bàn về available
                if ($lockedReservation->table_id) {
                    RestaurantTable::where('id', $lockedReservation->table_id)
                        ->where('status', 'reserved') // Chỉ reset nếu bàn đang ở trạng thái reserved (không động đến bàn đang có khách)
                        ->update(['status' => 'available']);
                }

                AuditLog::log(
                    'reservation_no_show',
                    'updated',
                    $lockedReservation,
                    ['status' => 'confirmed'],
                    ['status' => 'no_show', 'table_released' => (bool) $lockedReservation->table_id],
                );
            });
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "Đã đánh dấu không đến cho đặt bàn của {$reservation->guest_name}.");
    }

    /**
     * Khách tự đặt bàn qua trang QR hoặc website (public endpoint).
     */
    public function publicStore(Request $request, int $restaurantId): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:100'],
            'guest_phone' => ['required', 'string', 'max:20'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'reservation_time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:50'],
            'special_requests' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'in:qr,website,phone'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        // Kiểm tra nhà hàng tồn tại và đang hoạt động
        $restaurant = Restaurant::findOrFail($restaurantId);
        $activeBranches = $restaurant->branches()->where('status', 'active')->orderBy('id')->get(['id']);
        $branchId = $data['branch_id'] ?? null;

        if ($branchId !== null && ! $activeBranches->contains('id', (int) $branchId)) {
            $message = 'Chi nhánh không thuộc nhà hàng hoặc đang ngừng hoạt động.';

            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->withErrors(['branch_id' => $message]);
        }

        if ($branchId === null && $activeBranches->count() === 1) {
            $branchId = (int) $activeBranches->first()->id;
        }

        if ($branchId === null && $activeBranches->count() > 1) {
            $message = 'Vui lòng chọn chi nhánh trước khi đặt bàn.';

            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->withErrors(['branch_id' => $message]);
        }

        // Chuẩn hoá 'H:i' → 'H:i:s' để giá trị ghi xuống và giá trị so sánh luôn
        // cùng một dạng, không phụ thuộc engine tự ép kiểu cột TIME.
        $normalizedTime = $data['reservation_time'].':00';

        try {
            $reservation = DB::transaction(function () use ($restaurantId, $data, $normalizedTime, $branchId) {
                // Khóa dòng restaurant để tuần tự hóa việc tạo đặt bàn đồng thời cho nhà hàng đó
                Restaurant::where('id', $restaurantId)->lockForUpdate()->firstOrFail();

                // Kiểm tra có bàn trống không trong ngày/giờ đó
                // whereDate thay vì so sánh chuỗi thẳng: Eloquent ghi cột date kèm
                // phần giờ ('2026-07-31 00:00:00'), MySQL tự cắt bớt nhưng SQLite thì
                // không — so sánh thẳng đếm ra 0 và cho đặt vượt số bàn hiện có.
                // Giờ được chuẩn hoá về 'H:i:s' ngay khi ghi (xem create bên dưới)
                // nên so sánh bằng luôn đúng trên mọi engine.
                $existingCount = TableReservation::where('restaurant_id', $restaurantId)
                    ->when($branchId !== null, fn ($query) => $query->where(function ($q) use ($branchId) {
                        $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    }))
                    ->whereDate('reservation_date', $data['reservation_date'])
                    ->where('reservation_time', $normalizedTime)
                    ->whereIn('status', ['pending', 'confirmed', 'seated'])
                    ->lockForUpdate()
                    ->count();

                $totalTables = RestaurantTable::where('restaurant_id', $restaurantId)
                    ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                    ->where('status', '!=', 'inactive')
                    ->count();
                if ($existingCount >= $totalTables) {
                    throw new \Exception('Khung giờ này đã hết bàn trống. Vui lòng chọn giờ khác.');
                }

                return TableReservation::create([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'guest_name' => $data['guest_name'],
                    'guest_phone' => $data['guest_phone'],
                    'guest_email' => $data['guest_email'] ?? null,
                    'reservation_date' => $data['reservation_date'],
                    'reservation_time' => $normalizedTime,
                    'party_size' => $data['party_size'],
                    'special_requests' => $data['special_requests'] ?? null,
                    'source' => $data['source'] ?? 'qr',
                    'status' => 'pending',
                ]);
            });
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['reservation_time' => $e->getMessage()]);
        }

        try {
            app(WebhookDispatchService::class)->dispatch(
                $restaurantId,
                'reservation.created',
                [
                    'reservation_id' => $reservation->id,
                    'guest_name' => $reservation->guest_name,
                    'reservation_date' => $data['reservation_date'],
                    'reservation_time' => $data['reservation_time'],
                    'party_size' => (int) $data['party_size'],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('publicStore: lỗi webhook reservation.created', ['error' => $e->getMessage()]);
        }

        // Đặt cọc giữ bàn: nhà hàng có cấu hình mức cọc + có cổng thanh toán khả dụng
        $deposit = $this->createDepositPayment($reservation, $restaurant);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $deposit
                    ? 'Đặt bàn thành công! Vui lòng thanh toán cọc '.number_format($deposit['amount']).'đ để giữ chỗ.'
                    : 'Đặt bàn thành công! Nhà hàng sẽ xác nhận trong vòng 15 phút.',
                'reservation_id' => $reservation->id,
                'deposit' => $deposit,
            ]);
        }

        if ($deposit && $deposit['payment_url']) {
            return back()
                ->with('success', 'Đặt bàn thành công! Vui lòng thanh toán cọc '.number_format($deposit['amount']).'đ để giữ chỗ.')
                ->with('deposit_payment_url', $deposit['payment_url']);
        }

        return back()->with('success', 'Đặt bàn thành công! Nhà hàng sẽ sớm liên hệ xác nhận.');
    }

    /**
     * Tạo đơn cọc + link thanh toán qua cổng có sẵn. Trả về null nếu nhà hàng
     * không yêu cầu cọc hoặc chưa cấu hình cổng thanh toán nào (đặt bàn vẫn
     * thành công như bình thường — không được chặn khách vì thiếu cấu hình).
     */
    private function createDepositPayment(TableReservation $reservation, Restaurant $restaurant): ?array
    {
        $amount = (float) ($restaurant->reservation_deposit_amount ?? 0);

        if ($amount <= 0) {
            return null;
        }

        try {
            $gatewayService = app(PaymentGatewayService::class);
            $gateways = $gatewayService->getAvailableGateways($restaurant->id);

            if (empty($gateways)) {
                return null;
            }

            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $reservation->branch_id
                    ?? $restaurant->branches()->where('status', 'active')->orderBy('id')->value('id'),
                'order_number' => 'COC'.strtoupper(Str::random(8)),
                'channel' => 'reservation_deposit',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'subtotal' => $amount,
                'discount_amount' => 0,
                'total_amount' => $amount,
                'note' => "Cọc giữ bàn #{$reservation->id} — {$reservation->guest_name} ({$reservation->reservation_date->format('d/m/Y')} {$reservation->reservation_time})",
            ]);

            $reservation->update([
                'deposit_amount' => $amount,
                'deposit_status' => 'pending',
                'deposit_order_id' => $order->id,
            ]);

            $gatewayKey = $gateways[0]['key'];
            $paymentUrl = $gatewayService->createPayment($order, $gatewayKey, url('/'));

            return [
                'amount' => $amount,
                'gateway' => $gatewayKey,
                'payment_url' => $paymentUrl,
            ];
        } catch (\Throwable $e) {
            Log::warning('publicStore: không tạo được thanh toán cọc', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
