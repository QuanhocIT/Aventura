<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Responses\CustomLoginResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// Google OAuth (chỉ cho guest – user đã đăng nhập redirect về dashboard)
Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

Route::get('/', function () {
    $user = auth()->user();
    if ($user) {
        return CustomLoginResponse::redirectForUser($user);
    }
    return Inertia::render('Khach', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
