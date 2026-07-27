<?php

namespace App\Http\Controllers;

use App\Models\DemoBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DemoBookingController extends Controller
{
    /**
     * Tiếp nhận đăng ký đặt lịch demo từ khách hàng công khai.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'phone'           => 'required|string|max:20',
            'email'           => 'nullable|email|max:150',
            'restaurant_name' => 'required|string|max:150',
            'branch_count'    => 'nullable|integer|min:1|max:500',
            'preferred_date'  => 'nullable|date|after_or_equal:today',
            'preferred_time'  => 'nullable|string|max:50',
            'notes'           => 'nullable|string|max:1000',
        ], [
            'name.required'            => 'Vui lòng nhập họ và tên.',
            'phone.required'           => 'Vui lòng nhập số điện thoại liên hệ.',
            'restaurant_name.required' => 'Vui lòng nhập tên nhà hàng hoặc thương hiệu của bạn.',
            'email.email'              => 'Địa chỉ email không đúng định dạng.',
            'preferred_date.after_or_equal' => 'Ngày tư vấn phải từ ngày hôm nay trở đi.',
        ]);

        $booking = DemoBooking::create([
            'name'            => trim($validated['name']),
            'phone'           => trim($validated['phone']),
            'email'           => !empty($validated['email']) ? trim($validated['email']) : null,
            'restaurant_name' => trim($validated['restaurant_name']),
            'branch_count'    => (int) ($validated['branch_count'] ?? 1),
            'preferred_date'  => $validated['preferred_date'] ?? null,
            'preferred_time'  => $validated['preferred_time'] ?? null,
            'notes'           => !empty($validated['notes']) ? trim($validated['notes']) : null,
            'status'          => 'pending',
        ]);

        Log::info("Demo booking received: #{$booking->id} - {$booking->name} ({$booking->phone}) - Restaurant: {$booking->restaurant_name}");

        return response()->json([
            'success' => true,
            'message' => 'Cảm ơn bạn! Yêu cầu đặt lịch demo đã được ghi nhận. Chuyên gia tư vấn của Aventura sẽ liên hệ với bạn trong thời gian sớm nhất.',
            'booking_id' => $booking->id,
        ], 201);
    }
}
