<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DemoBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DemoBookingManagementController extends Controller
{
    /**
     * Danh sách các đơn đặt lịch demo cho SuperAdmin.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status');
        $search = $request->input('search');

        $query = DemoBooking::with('contactedBy:id,name,email')
            ->latest('id');

        if ($status && in_array($status, ['pending', 'contacted', 'completed', 'cancelled'])) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('restaurant_name', 'like', "%{$search}%");
            });
        }

        $bookings = $query->paginate(10)->withQueryString();

        $stats = [
            'total' => DemoBooking::count(),
            'pending' => DemoBooking::where('status', 'pending')->count(),
            'contacted' => DemoBooking::where('status', 'contacted')->count(),
            'completed' => DemoBooking::where('status', 'completed')->count(),
        ];

        return Inertia::render('super-admin/demo-bookings/Index', [
            'bookings' => $bookings,
            'stats' => $stats,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Cập nhật trạng thái đơn đặt lịch demo và ghi chú của chuyên gia.
     */
    public function update(Request $request, DemoBooking $demoBooking): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,contacted,completed,cancelled',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $updateData = [
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
        ];

        if ($validated['status'] !== 'pending' && ! $demoBooking->contacted_at) {
            $updateData['contacted_at'] = now();
            $updateData['contacted_by'] = $request->user()->id;
        }

        $demoBooking->update($updateData);

        return back()->with('success', 'Đã cập nhật trạng thái lịch hẹn demo thành công.');
    }

    /**
     * Xóa đơn đăng ký demo.
     */
    public function destroy(DemoBooking $demoBooking): RedirectResponse
    {
        $demoBooking->delete();

        return back()->with('success', 'Đã xóa lịch hẹn demo.');
    }
}
