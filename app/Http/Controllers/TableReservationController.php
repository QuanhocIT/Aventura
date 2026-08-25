<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Services\EmailMicroserviceClient;
use App\Services\Integrations\WebhookDispatchService;
use App\Services\PaymentGatewayService;
use App\Services\Sms\SmsService;
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
        $search = trim((string) $request->get('search', ''));

        $query = TableReservation::with(['table', 'customer', 'confirmedBy', 'branch'])
            ->where('restaurant_id', $rid)
            ->where('reservation_date', $dateFilter)
            ->when($branchId !== null, fn ($query) => $query->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $like = '%'.$search.'%';
                    $q->where('guest_name', 'like', $like)
                        ->orWhere('guest_phone', 'like', $like)
                        ->orWhere('guest_email', 'like', $like);
                });
            });

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
            'branch_id' => $r->branch_id,
            'branch_name' => $r->branch?->name,
            'confirmed_by_name' => $r->confirmedBy?->name,
            'source' => $r->source,
            'deposit_amount' => (float) ($r->deposit_amount ?? 0),
            'deposit_status' => $r->deposit_status ?? 'none',
        ]);

        // Thống kê theo ngày đang xem để KPI luôn khớp với danh sách.
        $todayStats = TableReservation::where('restaurant_id', $rid)
            ->where('reservation_date', $dateFilter)
            ->when($branchId !== null, fn ($query) => $query->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $tableStats = RestaurantTable::where('restaurant_id', $rid)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', '!=', 'inactive')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $availableTables = RestaurantTable::where('restaurant_id', $rid)
            ->where('status', 'available')
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->get(['id', 'name', 'capacity', 'branch_id', 'status']);

        $tables = RestaurantTable::where('restaurant_id', $rid)
            ->where('status', '!=', 'inactive')
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'capacity', 'branch_id', 'status']);

        $branches = RestaurantBranch::where('restaurant_id', $rid)
            ->where('status', 'active')
            ->when($branchId !== null, fn ($query) => $query->whereKey($branchId))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('reservations/Index', [
            'reservations' => $reservations,
            'todayStats' => $todayStats,
            'tableStats' => $tableStats,
            'availableTables' => $availableTables,
            'tables' => $tables,
            'branches' => $branches,
            'filters' => [
                'date' => $dateFilter,
                'status' => $statusFilter,
                'search' => $search,
                'branch_id' => $branchId,
            ],
        ]);
    }

    /**
     * Ghi nhận một cuộc gọi đặt bàn từ phía nhà hàng.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('approve_requests'), 403);

        $activeBranchId = app(TenantContext::class)->activeBranchId();
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'guest_name' => ['required', 'string', 'max:100'],
            'guest_phone' => ['required', 'string', 'max:20'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'reservation_time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:50'],
            'special_requests' => ['nullable', 'string', 'max:500'],
            'internal_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $requestedBranchId = $activeBranchId ?? ($data['branch_id'] ?? null);
        if ($activeBranchId !== null && isset($data['branch_id']) && (int) $data['branch_id'] !== (int) $activeBranchId) {
            abort(403, 'Bạn chỉ được ghi nhận đặt bàn cho chi nhánh đang được phân quyền.');
        }

        $branches = RestaurantBranch::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->when($activeBranchId !== null, fn ($query) => $query->whereKey($activeBranchId))
            ->get(['id']);

        if ($requestedBranchId === null && $branches->count() === 1) {
            $requestedBranchId = (int) $branches->first()->id;
        }

        if ($requestedBranchId === null || ! $branches->contains('id', (int) $requestedBranchId)) {
            return back()->withErrors([
                'branch_id' => 'Vui lòng chọn chi nhánh hợp lệ trước khi ghi nhận đặt bàn.',
            ]);
        }

        $normalizedTime = $data['reservation_time'].':00';

        try {
            $reservation = DB::transaction(function () use ($user, $data, $requestedBranchId, $normalizedTime) {
                Restaurant::whereKey($user->restaurant_id)->lockForUpdate()->firstOrFail();

                $existingCount = TableReservation::withoutGlobalScopes()
                    ->where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $requestedBranchId)
                    ->whereDate('reservation_date', $data['reservation_date'])
                    ->where('reservation_time', $normalizedTime)
                    ->whereIn('status', ['pending', 'confirmed', 'seated'])
                    ->count();

                $tableCount = RestaurantTable::withoutGlobalScopes()
                    ->where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $requestedBranchId)
                    ->where('status', '!=', 'inactive')
                    ->count();

                if ($tableCount === 0 || $existingCount >= $tableCount) {
                    throw new \Exception('Khung giờ này đã hết bàn trống tại chi nhánh đã chọn.');
                }

                $reservation = TableReservation::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $requestedBranchId,
                    'guest_name' => $data['guest_name'],
                    'guest_phone' => $data['guest_phone'],
                    'guest_email' => $data['guest_email'] ?? null,
                    'reservation_date' => $data['reservation_date'],
                    'reservation_time' => $normalizedTime,
                    'party_size' => $data['party_size'],
                    'special_requests' => $data['special_requests'] ?? null,
                    'internal_notes' => $data['internal_notes'] ?? 'Khách gọi điện đặt bàn.',
                    'source' => 'phone',
                    'status' => 'pending',
                ]);

                AuditLog::log(
                    'reservation_created',
                    'created',
                    $reservation,
                    null,
                    ['source' => 'phone', 'branch_id' => $requestedBranchId],
                );

                return $reservation;
            });
        } catch (\Exception $e) {
            return back()->withErrors(['reservation_time' => $e->getMessage()]);
        }

        return back()
            ->with('success', "Đã ghi nhận yêu cầu đặt bàn của khách {$reservation->guest_name}.")
            ->with('reservation_date', $data['reservation_date']);
    }

    /**
     * Xác nhận đặt bàn và assign bàn cụ thể.
     */
    public function confirm(Request $request, TableReservation $reservation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('approve_requests'), 403);
        $this->ensureReservationAccess($request, $reservation);

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

                    if ($table->capacity < $lockedReservation->party_size) {
                        throw new \Exception("Bàn {$table->name} chỉ có {$table->capacity} chỗ, không đủ cho {$lockedReservation->party_size} khách.");
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

        $this->notifyReservationGuest($reservation->fresh(['restaurant', 'table']), 'confirmed');

        return back()->with('success', "Đã xác nhận đặt bàn cho khách {$reservation->guest_name}.");
    }

    /**
     * Đánh dấu khách đã đến và được dẫn vào bàn.
     */
    public function seat(Request $request, TableReservation $reservation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('create_orders'), 403);
        $this->ensureReservationAccess($request, $reservation);

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
        $this->ensureReservationAccess($request, $reservation);

        $restaurantId = $user->restaurant_id;
        $table = null;

        try {
            DB::transaction(function () use ($reservation, $user, $restaurantId, &$table) {
                $lockedReservation = TableReservation::where('id', $reservation->id)
                    ->where('restaurant_id', $restaurantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedReservation->status !== 'pending') {
                    throw new \Exception('Chỉ có thể xếp bàn tự động cho đặt bàn đang chờ xác nhận.');
                }

                $branchId = app(TenantContext::class)->activeBranchId() ?? $lockedReservation->branch_id;

                $reservationDateTime = Carbon::parse(
                    $lockedReservation->reservation_date->toDateString().' '.$lockedReservation->reservation_time
                );
                $windowStart = $reservationDateTime->copy()->subMinutes(90)->format('H:i:s');
                $windowEnd = $reservationDateTime->copy()->addMinutes(90)->format('H:i:s');

                $conflictingTableIds = TableReservation::where('restaurant_id', $restaurantId)
                    ->where('reservation_date', $lockedReservation->reservation_date)
                    ->whereNotNull('table_id')
                    ->whereBetween('reservation_time', [$windowStart, $windowEnd])
                    ->whereIn('status', ['confirmed', 'seated'])
                    ->where('id', '!=', $lockedReservation->id)
                    ->pluck('table_id')
                    ->all();

                $tableQuery = RestaurantTable::where('restaurant_id', $restaurantId)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->where('status', '!=', 'inactive')
                    ->whereNotIn('id', $conflictingTableIds)
                    ->where('capacity', '>=', $lockedReservation->party_size)
                    ->orderBy('capacity', 'asc')
                    ->lockForUpdate();

                $table = $tableQuery->first();

                if (! $table) {
                    throw new \Exception("Không tìm thấy bàn trống phù hợp cho {$lockedReservation->party_size} khách tại chi nhánh trong khung giờ này.");
                }

                $lockedReservation->update([
                    'table_id' => $table->id,
                    'branch_id' => $lockedReservation->branch_id ?? $table->branch_id,
                    'status' => 'confirmed',
                    'confirmed_by' => $user->id,
                    'confirmed_at' => now(),
                ]);
                $table->update(['status' => 'reserved']);

                AuditLog::log(
                    'reservation_auto_assigned',
                    'updated',
                    $lockedReservation,
                    ['status' => 'pending'],
                    ['status' => 'confirmed', 'table_id' => $table->id, 'table_name' => $table->name],
                );
            });
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['table_id' => $e->getMessage()]);
        }

        $tableName = $table?->name ?? '—';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Đã tự động xếp bàn [{$tableName}] cho khách {$reservation->guest_name}!",
                'table_name' => $tableName,
                'reservation' => $reservation,
            ]);
        }

        $this->notifyReservationGuest($reservation->fresh(['restaurant', 'table']), 'confirmed');

        return back()->with('success', "Đã tự động xếp bàn [{$tableName}] cho khách {$reservation->guest_name}!");
    }

    /**
     * Hủy đặt bàn.
     */
    public function cancel(Request $request, TableReservation $reservation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('approve_requests'), 403);
        $this->ensureReservationAccess($request, $reservation);

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

        $this->notifyReservationGuest($reservation->fresh(['restaurant', 'table']), 'cancelled', $data['reason']);

        return back()->with('success', "Đã hủy đặt bàn của khách {$reservation->guest_name}.");
    }

    /**
     * Đánh dấu khách không đến (no-show).
     */
    public function noShow(Request $request, TableReservation $reservation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('approve_requests'), 403);
        $this->ensureReservationAccess($request, $reservation);

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
     * Đóng lượt đặt bàn sau khi khách đã dùng bữa/thanh toán.
     */
    public function complete(Request $request, TableReservation $reservation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('create_orders'), 403);
        $this->ensureReservationAccess($request, $reservation);

        try {
            DB::transaction(function () use ($reservation, $user) {
                $lockedReservation = TableReservation::where('id', $reservation->id)
                    ->where('restaurant_id', $user->restaurant_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedReservation->status !== 'seated') {
                    throw new \Exception('Chỉ có thể hoàn tất lượt khách đang phục vụ.');
                }

                $lockedReservation->update(['status' => 'completed']);

                if ($lockedReservation->table_id) {
                    RestaurantTable::whereKey($lockedReservation->table_id)
                        ->where('status', 'occupied')
                        ->update(['status' => 'available']);
                }

                AuditLog::log(
                    'reservation_completed',
                    'updated',
                    $lockedReservation,
                    ['status' => 'seated'],
                    ['status' => 'completed', 'table_released' => (bool) $lockedReservation->table_id],
                );
            });
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "Đã hoàn tất lượt khách của {$reservation->guest_name}.");
    }

    private function ensureReservationAccess(Request $request, TableReservation $reservation): void
    {
        abort_if($reservation->restaurant_id !== $request->user()->restaurant_id, 403);

        $activeBranchId = app(TenantContext::class)->activeBranchId();
        if ($activeBranchId !== null && $reservation->branch_id !== null && (int) $reservation->branch_id !== (int) $activeBranchId) {
            abort(403, 'Bạn không có quyền thao tác đặt bàn của chi nhánh khác.');
        }
    }

    /**
     * Khách tự đặt bàn qua trang QR hoặc website (public endpoint).
     */
    public function publicStatus(Request $request, int $restaurantId, string $reservationToken): Response|JsonResponse
    {
        $reservation = TableReservation::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('reservation_token', $reservationToken)
            ->with(['restaurant', 'table', 'branch'])
            ->firstOrFail();

        $payload = $this->publicReservationPayload($reservation);

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('reservations/PublicStatus', $payload);
    }

    public function publicCancel(Request $request, int $restaurantId, string $reservationToken): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'min:3', 'max:255'],
        ]);
        $reason = trim($data['reason'] ?? 'Khách tự hủy đặt bàn.');

        try {
            $reservation = DB::transaction(function () use ($restaurantId, $reservationToken, $reason) {
                $lockedReservation = TableReservation::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where('reservation_token', $reservationToken)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($lockedReservation->status, ['pending', 'confirmed'], true)) {
                    throw new \Exception('Đặt bàn này không còn có thể hủy.');
                }

                $oldStatus = $lockedReservation->status;
                $lockedReservation->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason,
                    'cancelled_at' => now(),
                ]);

                if ($lockedReservation->table_id) {
                    RestaurantTable::whereKey($lockedReservation->table_id)
                        ->where('restaurant_id', $restaurantId)
                        ->where('status', 'reserved')
                        ->update(['status' => 'available']);
                }

                AuditLog::log(
                    'reservation_cancelled_by_guest',
                    'updated',
                    $lockedReservation,
                    ['status' => $oldStatus],
                    ['status' => 'cancelled', 'reason' => $reason],
                );

                return $lockedReservation->fresh(['restaurant', 'table', 'branch']);
            });
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $this->notifyReservationGuest($reservation, 'cancelled', $reason);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã hủy đặt bàn thành công.',
                ...$this->publicReservationPayload($reservation),
            ]);
        }

        return back()
            ->with('success', 'Đã hủy đặt bàn thành công.')
            ->with('reservation_status_url', route('reservations.public-status', [$restaurantId, $reservation->reservation_token]));
    }

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
                'status_url' => route('reservations.public-status', [$restaurantId, $reservation->reservation_token]),
                'deposit' => $deposit,
            ]);
        }

        $statusUrl = route('reservations.public-status', [$restaurantId, $reservation->reservation_token]);

        if ($deposit && $deposit['payment_url']) {
            return back()
                ->with('success', 'Đặt bàn thành công! Vui lòng thanh toán cọc '.number_format($deposit['amount']).'đ để giữ chỗ.')
                ->with('deposit_payment_url', $deposit['payment_url'])
                ->with('reservation_status_url', $statusUrl);
        }

        return back()
            ->with('success', 'Đặt bàn thành công! Nhà hàng sẽ sớm liên hệ xác nhận.')
            ->with('reservation_status_url', $statusUrl);
    }

    /**
     * Tạo đơn cọc + link thanh toán qua cổng có sẵn. Trả về null nếu nhà hàng
     * không yêu cầu cọc hoặc chưa cấu hình cổng thanh toán nào (đặt bàn vẫn
     * thành công như bình thường — không được chặn khách vì thiếu cấu hình).
     */
    private function publicReservationPayload(TableReservation $reservation): array
    {
        return [
            'restaurant' => [
                'id' => $reservation->restaurant_id,
                'name' => $reservation->restaurant?->name ?? 'Nhà hàng',
            ],
            'reservation' => [
                'id' => $reservation->id,
                'guest_name' => $reservation->guest_name,
                'reservation_date' => $reservation->reservation_date?->toDateString(),
                'reservation_time' => substr((string) $reservation->reservation_time, 0, 5),
                'party_size' => (int) $reservation->party_size,
                'status' => $reservation->status,
                'status_label' => $reservation->status_label,
                'table_name' => $reservation->table?->name,
                'branch_name' => $reservation->branch?->name,
                'special_requests' => $reservation->special_requests,
                'cancellation_reason' => $reservation->cancellation_reason,
                'can_cancel' => in_array($reservation->status, ['pending', 'confirmed'], true),
            ],
        ];
    }

    private function notifyReservationGuest(TableReservation $reservation, string $status, ?string $reason = null): void
    {
        if (! $reservation->guest_email && ! $reservation->guest_phone) {
            return;
        }

        $reservation->loadMissing(['restaurant', 'table']);
        $statusUrl = route('reservations.public-status', [$reservation->restaurant_id, $reservation->reservation_token]);
        $common = [
            'recipient_email' => $reservation->guest_email,
            'recipient_name' => $reservation->guest_name,
            'restaurant_name' => $reservation->restaurant?->name ?? 'Nhà hàng',
            'reservation_date' => $reservation->reservation_date?->format('d/m/Y'),
            'reservation_time' => substr((string) $reservation->reservation_time, 0, 5),
            'party_size' => $reservation->party_size,
            'table_name' => $reservation->table?->name,
            'special_requests' => $reservation->special_requests,
            'internal_notes' => $reservation->internal_notes,
            'status_url' => $statusUrl,
            'manage_url' => $statusUrl,
        ];

        try {
            if ($reservation->guest_email) {
                $client = app(EmailMicroserviceClient::class);
                if ($status === 'confirmed') {
                    $client->sendReservationConfirmation($common);
                } elseif ($status === 'cancelled') {
                    $client->sendReservationCancellation($common + ['reason' => $reason]);
                }
            }

            if ($reservation->guest_phone) {
                $label = $status === 'confirmed' ? 'đã được nhà hàng xác nhận' : 'đã được hủy';
                app(SmsService::class)->send(
                    $reservation->guest_phone,
                    "Aventura: Đặt bàn ngày {$common['reservation_date']} lúc {$common['reservation_time']} {$label}. Xem chi tiết: {$statusUrl}"
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Reservation guest notification failed.', [
                'reservation_id' => $reservation->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

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
