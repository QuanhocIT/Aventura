<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('support.announcements', function ($user) {
    return (bool) $user;
});

// Supplier nhận PO mới từ nhà hàng
Broadcast::channel('supplier.{supplierId}', function ($user, $supplierId) {
    return $user->hasRole('supplier_admin') && (int) $user->supplier_id === (int) $supplierId;
});

// Bộ phận kho nhận cập nhật trạng thái PO từ supplier
Broadcast::channel('warehouse.{restaurantId}', function ($user, $restaurantId) {
    return (int) $user->restaurant_id === (int) $restaurantId
        && $user->hasAnyPermission(['manage_inventory', 'manage_restaurant_settings']);
});