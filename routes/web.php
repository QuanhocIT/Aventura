<?php

// ...existing code...
// Google OAuth routes
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Auth\GoogleController;

Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// ...existing code...
use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// Google OAuth (chỉ cho guest – user đã đăng nhập redirect về dashboard)
Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// Google OAuth (chỉ cho guest – user đã đăng nhập redirect về dashboard)
Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});
// ...existing code...
Route::inertia('/', 'Khach', [
    'canRegister' => Features::enabled(Features::registration()),
    'auth' => [
        'user' => null,
    ],
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('super-admin/dashboard', 'super-admin/DashboardSuperAdmin')->name('dashboard.superadmin');
});

require __DIR__.'/settings.php';
