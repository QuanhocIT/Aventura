<?php

namespace App\Http\Controllers;

use App\Models\TableReservation;
use App\Models\RestaurantTable;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $dateFilter   = $request->get('date', today()->toDateString());
        $statusFilter = $request->get('status', 'all');

        $query = TableReservation::with(['table', 'customer', 'confirmedBy'])
            ->where('restaurant_id', $rid)
            ->where('reservation_date', $dateFilter);

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $reservations = $query->orderBy('reservation_time')->get()->map(fn ($r) => [
            'id'                => $r->id,
            'guest_name'        => $r->guest_name,
            'guest_phone'       => $r->guest_phone,
            'guest_email'       => $r->guest_email,
            'reservation_date'  => $r->reservation_date->format('d/m/Y'),
            'reservation_time'  => $r->reservation_time,
            'party_size'        => $r->party_size,
            'status'            => $r->status,
            'status_label'      => $r->status_label,
            'status_color'      => $r->status_color,
            'special_requests'  => $r->special_requests,
            'internal_notes'    => $r->internal_notes,
            'table_name'        => $r->table?->name,
            'confirmed_by_name' => $r->confirmedBy?->name,
            'source'            => $r->source,
        ]);

        // Thống kê hôm nay
        $todayStats = TableReservation::where('restaurant_id', $rid)
            ->where('reservation_date', today())
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $availableTables = RestaurantTable::where('restaurant_id', $rid)
            ->where('status', 'available')
            ->get(['id', 'name', 'capacity']);

        return Inertia::render('reservations/Index', [
            'reservations'   => $reservations,
            'todayStats'     => $todayStats,
            'availableTables'=> $availableTables,
            'filters'        => ['date' => $dateFilter, 'status' => $statusFilter],
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
        abort_unless($reservation->status === 'pending', 422, 'Chỉ xác nhận được đặt bàn đang chờ.');

        $data = $request->validate([
            'table_id'       => ['nullable', "exists:restaurant_tables,id,restaurant_id,{$user->restaurant_id}"],
            'internal_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $reservation->update([
            'status'         => 'confirmed',
            'table_id'       => $data['table_id'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? $reservation->internal_notes,
            'confirmed_by'   => $user->id,
            'confirmed_at'   => now(),
        ]);

        // TODO: Gửi email xác nhận cho khách
        // app(EmailMicroserviceClient::class)->sendReservationConfirmation($reservation);

        \App\Models\AuditLog::create([
            'restaurant_id'  => $reservation->restaurant_id,
            'user_id'        => $user->id,
            'action'         => 'reservation_confirmed',
            'auditable_type' => TableReservation::class,
            'auditable_id'   => $reservation->id,
            'old_values'     => json_encode(['status' => 'pending']),
            'new_values'     => json_encode(['status' => 'confirmed', 'table_id' => $data['table_id'] ?? null]),
            'ip_address'     => $request->ip(),
        ]);

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
        abort_unless($reservation->status === 'confirmed', 422, 'Chỉ có thể dẫn khách vào bàn khi đặt đã được xác nhận.');

        $reservation->update([
            'status'    => 'seated',
            'seated_at' => now(),
        ]);

        // Cập nhật trạng thái bàn thành 'occupied' nếu có assign bàn
        if ($reservation->table_id) {
            RestaurantTable::where('id', $reservation->table_id)->update(['status' => 'occupied']);
        }

        return back()->with('success', "Đã dẫn khách {$reservation->guest_name} vào bàn.");
    }

    /**
     * Hủy đặt bàn.
     */
    public function cancel(Request $request, TableReservation $reservation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('approve_requests'), 403);
        abort_if($reservation->restaurant_id !== $user->restaurant_id, 403);
        abort_unless(in_array($reservation->status, ['pending', 'confirmed']), 422, 'Không thể hủy đặt bàn này.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $reservation->update([
            'status'               => 'cancelled',
            'cancellation_reason'  => $data['reason'],
            'cancelled_at'         => now(),
        ]);

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
        abort_unless($reservation->status === 'confirmed', 422, 'Chỉ đánh dấu no-show cho đặt bàn đã xác nhận.');

        $reservation->update([
            'status'       => 'no_show',
            'cancelled_at' => now(),
        ]);

        return back()->with('success', "Đã đánh dấu không đến cho đặt bàn của {$reservation->guest_name}.");
    }

    /**
     * Khách tự đặt bàn qua trang QR hoặc website (public endpoint).
     */
    public function publicStore(Request $request, int $restaurantId): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'guest_name'       => ['required', 'string', 'max:100'],
            'guest_phone'      => ['required', 'string', 'max:20'],
            'guest_email'      => ['nullable', 'email', 'max:255'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'reservation_time' => ['required', 'date_format:H:i'],
            'party_size'       => ['required', 'integer', 'min:1', 'max:50'],
            'special_requests' => ['nullable', 'string', 'max:500'],
            'source'           => ['nullable', 'in:qr,website,phone'],
        ]);

        // Kiểm tra nhà hàng tồn tại và đang hoạt động
        $restaurant = \App\Models\Restaurant::findOrFail($restaurantId);

        // Kiểm tra có bàn trống không trong ngày/giờ đó
        $existingCount = TableReservation::where('restaurant_id', $restaurantId)
            ->where('reservation_date', $data['reservation_date'])
            ->where('reservation_time', $data['reservation_time'])
            ->whereIn('status', ['pending', 'confirmed', 'seated'])
            ->count();

        $totalTables = RestaurantTable::where('restaurant_id', $restaurantId)->count();
        if ($existingCount >= $totalTables) {
            return back()->withErrors(['reservation_time' => 'Khung giờ này đã hết bàn trống. Vui lòng chọn giờ khác.']);
        }

        $reservation = TableReservation::create([
            'restaurant_id'    => $restaurantId,
            'guest_name'       => $data['guest_name'],
            'guest_phone'      => $data['guest_phone'],
            'guest_email'      => $data['guest_email'] ?? null,
            'reservation_date' => $data['reservation_date'],
            'reservation_time' => $data['reservation_time'],
            'party_size'       => $data['party_size'],
            'special_requests' => $data['special_requests'] ?? null,
            'source'           => $data['source'] ?? 'qr',
            'status'           => 'pending',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Đặt bàn thành công! Nhà hàng sẽ xác nhận trong vòng 15 phút.',
                'reservation_id' => $reservation->id,
            ]);
        }

        return back()->with('success', 'Đặt bàn thành công! Nhà hàng sẽ sớm liên hệ xác nhận.');
    }
}
