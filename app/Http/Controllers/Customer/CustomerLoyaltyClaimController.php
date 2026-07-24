<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CustomerLoyaltyClaimController extends Controller
{
    /**
     * Lấy thông báo chúc mừng tích điểm và câu hỏi lưu điểm trên màn hình khách sau khi thanh toán hóa đơn.
     */
    public function getOrderPointsSummary(Order $order): JsonResponse
    {
        $order->loadMissing(['restaurant', 'items.product']);

        $totalEarnedPoints = $order->calculateTotalEarnPoints();
        $restaurantName = $order->restaurant?->name ?? 'Aventura Restaurant';

        return response()->json([
            'success'                => true,
            'order_id'               => $order->id,
            'order_number'           => $order->order_number,
            'restaurant_id'          => $order->restaurant_id,
            'restaurant_name'        => $restaurantName,
            'total_items'            => $order->items->count(),
            'total_amount'           => (float) $order->total_amount,
            'total_earned_points'    => $totalEarnedPoints,
            'congratulations_title'  => "🎉 Chúc mừng quý khách!",
            'congratulations_message'=> "Hóa đơn #{$order->order_number} của quý khách có {$order->items->count()} món ăn và tích lũy thành công +{$totalEarnedPoints} điểm thưởng!",
            'ask_save_message'       => "Quý khách có muốn lưu +{$totalEarnedPoints} điểm thưởng này vào tài khoản hội viên của {$restaurantName} để đổi món ăn miễn phí cho lần tới không?",
            'already_claimed'        => !is_null($order->customer_id),
        ]);
    }

    /**
     * Khách hàng Đăng nhập / Đăng ký nhanh để lưu số điểm tích lũy của hóa đơn.
     */
    public function claimPoints(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id'  => ['required', 'exists:orders,id'],
            'phone'     => ['required', 'string', 'regex:/^[0-9]{9,12}$/'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'password'  => ['nullable', 'string', 'min:4'],
        ]);

        $order = Order::findOrFail($data['order_id']);
        $restaurantId = $order->restaurant_id;

        $totalEarnedPoints = $order->calculateTotalEarnPoints();

        // 1. Tìm hoặc Khởi tạo Hồ sơ Khách hàng (Customer) theo restaurant_id (Tenant Isolation)
        $customer = Customer::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('phone', $data['phone'])
            ->first();

        $isNewRegistration = false;

        if (!$customer) {
            $isNewRegistration = true;
            $customer = Customer::create([
                'restaurant_id'  => $restaurantId,
                'branch_id'      => $order->branch_id,
                'full_name'      => !empty($data['full_name']) ? $data['full_name'] : 'Khách Hàng (' . substr($data['phone'], -4) . ')',
                'phone'          => $data['phone'],
                'loyalty_points' => 0,
            ]);
        } else {
            if (!empty($data['full_name']) && ($customer->full_name === 'Khách Hàng' || str_contains($customer->full_name, 'Khách Hàng ('))) {
                $customer->update(['full_name' => $data['full_name']]);
            }
        }

        // 2. Cắm cờ / Khởi tạo Tài khoản User Khách Hàng cắm cờ theo Doanh nghiệp/Nhà hàng
        $user = User::where('phone', $data['phone'])->first();
        if (!$user) {
            $user = User::create([
                'name'          => $customer->full_name,
                'email'         => $data['phone'] . '@customer.local',
                'phone'         => $data['phone'],
                'password'      => Hash::make($data['password'] ?? '123456'),
                'restaurant_id' => $restaurantId,
                'branch_id'     => $order->branch_id,
                'status'        => 'active',
            ]);

            $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
            $user->assignRole($customerRole);
        }

        // 3. Liên kết hóa đơn với Customer
        $order->update(['customer_id' => $customer->id]);

        // 4. Cộng điểm tích lũy của toàn bộ hóa đơn cho Khách hàng
        $loyaltyService = app(LoyaltyService::class);
        $pointsEarned = $loyaltyService->earnPoints($customer, $order, (float) $order->total_amount);

        $customer->refresh();

        return response()->json([
            'success'         => true,
            'message'         => "Chúc mừng! Đã lưu thành công +{$pointsEarned} điểm vào tài khoản hội viên của quý khách.",
            'is_new_customer' => $isNewRegistration,
            'customer'        => [
                'id'                 => $customer->id,
                'full_name'          => $customer->full_name,
                'phone'              => $customer->phone,
                'total_points'       => (int) $customer->loyalty_points,
                'restaurant_id'      => (int) $customer->restaurant_id,
                'is_customer_flag'   => true,
                'customer_role_name' => 'customer',
            ],
            'points_added'    => $pointsEarned,
        ]);
    }
}
