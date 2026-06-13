<?php

use App\Events\Support\SupportAnnouncementPublished;
use App\Models\SupportAnnouncement;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('support.announcements', function ($user) {
    return (bool) $user;
});

// Delivery management channel — only staff of the same restaurant can join
Broadcast::channel('delivery.{restaurantId}', function ($user, int $restaurantId) {
    return (int) $user->restaurant_id === $restaurantId;
});