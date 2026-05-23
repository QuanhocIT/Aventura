<?php

use App\Http\Controllers\SuperAdmin\AccountController;
use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Http\Controllers\SuperAdmin\BillingOverrideController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\RestaurantController;
use App\Http\Controllers\SuperAdmin\SubscriptionPlanController;
use App\Http\Controllers\SuperAdmin\SupportPortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('super-admin')
    ->name('superadmin.')
    ->middleware(['auth', 'verified', 'permission.cache.clear', 'role:super_admin|admin'])
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('billing/export', [BillingController::class, 'exportCsv'])->name('billing.export');
        Route::post('billing/invoices/{invoice}/resend', [BillingController::class, 'resendInvoice'])->name('billing.invoices.resend');
        Route::post('billing/invoices/{invoice}/regenerate', [BillingController::class, 'regenerateInvoice'])->name('billing.invoices.regenerate');
        Route::post('billing/webhooks/{webhook}/retry', [BillingController::class, 'retryWebhook'])->name('billing.webhooks.retry');

        Route::get('restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
        Route::post('restaurants', [RestaurantController::class, 'store'])->name('restaurants.store');
        Route::get('restaurants/{restaurant}', [RestaurantController::class, 'show'])->name('restaurants.show');
        Route::patch('restaurants/{restaurant}/status', [RestaurantController::class, 'updateStatus'])->name('restaurants.status');
        Route::patch('restaurants/{restaurant}/plan', [RestaurantController::class, 'updatePlan'])->name('restaurants.plan');
        Route::post('restaurants/{restaurant}/billing-overrides', [BillingOverrideController::class, 'store'])->name('restaurants.billing-overrides.store');

        Route::get('plans', [SubscriptionPlanController::class, 'index'])->name('plans.index');
        Route::patch('plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('plans.update');

        Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::post('accounts/{user}/reset-password', [AccountController::class, 'resetPassword'])->name('accounts.reset-password');
        Route::post('accounts/{user}/disable-2fa', [AccountController::class, 'disable2FA'])->name('accounts.disable-2fa');
        Route::patch('accounts/{user}/status', [AccountController::class, 'toggleStatus'])->name('accounts.status');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('support', [SupportPortalController::class, 'index'])->name('support.index');
        Route::post('support/tickets', [SupportPortalController::class, 'storeTicket'])->name('support.tickets.store');
        Route::patch('support/tickets/{ticket}', [SupportPortalController::class, 'updateTicket'])->name('support.tickets.update');
        Route::post('support/announcements', [SupportPortalController::class, 'storeAnnouncement'])->name('support.announcements.store');
        Route::post('support/articles', [SupportPortalController::class, 'storeArticle'])->name('support.articles.store');
        Route::post('support/rules', [SupportPortalController::class, 'storeRule'])->name('support.rules.store');
        Route::post('support/alerts/run', [SupportPortalController::class, 'runAlertCheck'])->name('support.alerts.run');
    });