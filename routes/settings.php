<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RestaurantController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/device-token', [ProfileController::class, 'updateDeviceToken'])->name('profile.device-token.update');

    Route::get('settings/restaurant', [RestaurantController::class, 'edit'])->name('restaurant.edit');
    Route::patch('settings/restaurant', [RestaurantController::class, 'update'])->name('restaurant.update');

    Route::get('settings/referrals', [\App\Http\Controllers\Settings\ReferralSettingsController::class, 'edit'])->name('settings.referrals.edit');
    Route::post('settings/referrals/withdraw', [\App\Http\Controllers\Settings\ReferralSettingsController::class, 'withdraw'])->name('settings.referrals.withdraw');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});
