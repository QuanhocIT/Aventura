<?php

use App\Http\Controllers\SuperAdmin\AccountController;
use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\RestaurantController;
use App\Http\Controllers\SuperAdmin\SubscriptionPlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('super-admin')
    ->name('superadmin.')
    ->middleware(['auth', 'verified', 'role:admin'])
    ->group(function () {

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Quản lý nhà hàng
        Route::get('restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
        Route::post('restaurants', [RestaurantController::class, 'store'])->name('restaurants.store');
        Route::get('restaurants/{restaurant}', [RestaurantController::class, 'show'])->name('restaurants.show');
        Route::patch('restaurants/{restaurant}/status', [RestaurantController::class, 'updateStatus'])->name('restaurants.status');
        Route::patch('restaurants/{restaurant}/plan', [RestaurantController::class, 'updatePlan'])->name('restaurants.plan');

        // Quản lý gói dịch vụ
        Route::get('plans', [SubscriptionPlanController::class, 'index'])->name('plans.index');
        Route::patch('plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('plans.update');

        // Quản lý tài khoản & bảo mật
        Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::post('accounts/{user}/reset-password', [AccountController::class, 'resetPassword'])->name('accounts.reset-password');
        Route::post('accounts/{user}/disable-2fa', [AccountController::class, 'disable2FA'])->name('accounts.disable-2fa');
        Route::patch('accounts/{user}/status', [AccountController::class, 'toggleStatus'])->name('accounts.status');

        // Audit Log hệ thống
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
