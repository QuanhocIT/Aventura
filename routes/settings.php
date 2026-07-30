<?php

use App\Http\Controllers\OnboardingController;
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

    Route::get('settings/branches', [\App\Http\Controllers\Settings\BranchController::class, 'index'])->name('branches.index');
    Route::post('settings/branches', [\App\Http\Controllers\Settings\BranchController::class, 'store'])->name('branches.store')->middleware('tenant.quota:branches');
    Route::patch('settings/branches/{branch}', [\App\Http\Controllers\Settings\BranchController::class, 'update'])->name('branches.update');
    Route::delete('settings/branches/{branch}', [\App\Http\Controllers\Settings\BranchController::class, 'destroy'])->name('branches.destroy');

    Route::get('settings/referrals', function () {
        return redirect()->route('profile.edit', ['tab' => 'referrals']);
    })->name('settings.referrals.edit');
    Route::post('settings/referrals/withdraw', [\App\Http\Controllers\Settings\ReferralSettingsController::class, 'withdraw'])->name('settings.referrals.withdraw');

    // Sandbox & Demo Data
    Route::get('settings/sandbox', [OnboardingController::class, 'sandboxPage'])->name('settings.sandbox');
    Route::post('settings/sandbox/toggle', [OnboardingController::class, 'toggleSandbox'])->name('sandbox.toggle');
    Route::post('settings/sandbox/seed', [OnboardingController::class, 'seedDemo'])->name('sandbox.seed');
    Route::post('settings/sandbox/reset', [OnboardingController::class, 'resetDemo'])->name('sandbox.reset');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', function () {
        return redirect()->route('profile.edit', ['tab' => 'security']);
    })->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::put('settings/pin', [SecurityController::class, 'updatePin'])
        ->middleware('throttle:6,1')
        ->name('user-pin.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});
