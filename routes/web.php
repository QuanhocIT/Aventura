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
});

require __DIR__.'/settings.php';

Route::get('/debug-auth', function () {
    $user = auth()->user();
    if (!$user) {
        return 'Not logged in';
    }
    try {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    } catch (\Throwable $e) {}
    
    return [
        'user_id' => $user->id,
        'email' => $user->email,
        'roles_relation_raw' => \DB::table('model_has_roles')->where('model_id', $user->id)->get(),
        'roles_relation' => $user->roles()->pluck('name'),
        'has_super_admin' => $user->hasRole('super_admin'),
        'has_admin' => $user->hasRole('admin'),
        'roles_spatie_cache' => $user->getRoleNames(),
    ];
});

// Route tạm để gán role super_admin — XÓA SAU KHI DÙNG
Route::get('/fix-superadmin-role', function () {
    $user = \App\Models\User::where('email', 'superadmin@aventura.local')->first();
    if (!$user) {
        return 'User not found';
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate(
        ['name' => 'super_admin', 'guard_name' => 'web']
    );

    if (!$user->hasRole('super_admin')) {
        $user->assignRole('super_admin');
        $assigned = true;
    } else {
        $assigned = false;
    }

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return [
        'user_id' => $user->id,
        'email' => $user->email,
        'super_admin_role_id' => $superAdminRole->id,
        'assigned_now' => $assigned,
        'final_roles' => $user->fresh()->getRoleNames(),
        'message' => 'Done! Now login again at /login',
    ];
});

