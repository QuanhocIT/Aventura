<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Rule `exists:` có giới hạn tenant.
 *
 * Vấn đề: 'exists:products,id' kiểm tra ID tồn tại TOÀN HỆ THỐNG — kẻ tấn công
 * đoán ID của nhà hàng khác vẫn vượt qua validation, sau đó nếu controller
 * ghi thẳng ID đó vào create()/update() sẽ tạo tham chiếu chéo tenant.
 *
 * Dùng: TenantRule::exists('products') thay cho 'exists:products,id'.
 * Với luồng khách (không đăng nhập user nhà hàng), truyền restaurant_id
 * đã resolve từ context: TenantRule::exists('products', restaurantId: $rid).
 */
class TenantRule
{
    public static function exists(string $table, string $column = 'id', ?int $restaurantId = null): Exists
    {
        $restaurantId ??= (int) Auth::user()?->restaurant_id;

        return Rule::exists($table, $column)->where('restaurant_id', $restaurantId);
    }
}
