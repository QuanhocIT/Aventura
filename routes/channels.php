<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('support.announcements', function ($user) {
    return (bool) $user;
});

Broadcast::channel('superadmin.campaigns', function ($user) {
    return method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin();
});

Broadcast::channel('restaurant.{restaurantId}.campaigns.{audience}', function ($user, $restaurantId, $audience) {
    if ((int) ($user->restaurant_id ?? 0) !== (int) $restaurantId) {
        return false;
    }

    return $audience === 'all_staff'
        || ($audience === 'owner' && method_exists($user, 'isOwner') && $user->isOwner());
});

Broadcast::channel('superadmin.dashboard', function ($user) {
    return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
});

Broadcast::channel('restaurant.{restaurantId}', function ($user, $restaurantId) {
    return (int) $user->restaurant_id === (int) $restaurantId;
});

Broadcast::channel('kitchen.{restaurantId}', function ($user, $restaurantId) {
    return (int) $user->restaurant_id === (int) $restaurantId;
});

Broadcast::channel('supplier.{supplierId}', function ($user, $supplierId) {
    return (int) ($user->supplier_id ?? 0) === (int) $supplierId;
});

// Smart Routing & Dispatch — private delivery channel per restaurant
Broadcast::channel('delivery.{restaurantId}', function ($user, $restaurantId) {
    return (int) $user->restaurant_id === (int) $restaurantId;
});
