<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Billing\PaymentWebhookController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::post('webhooks/payments', PaymentWebhookController::class)->name('billing.webhook');

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

Route::inertia('/', 'Khach', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified', 'tenant.subscription'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('super-admin/dashboard', 'super-admin/DashboardSuperAdmin')->name('dashboard.superadmin');
});

require __DIR__.'/settings.php';
