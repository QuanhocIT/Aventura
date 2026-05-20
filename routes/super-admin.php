<?php

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
    });
