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