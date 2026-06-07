<?php

use App\Http\Controllers\SuperAdmin\AccountController;
use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\RestaurantController;
use App\Http\Controllers\SuperAdmin\SubscriptionPlanController;
use App\Http\Controllers\SuperAdmin\SupportPortalController;
use App\Http\Controllers\SuperAdmin\BannerController;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Http\Controllers\SuperAdmin\BillingOverrideController;
use App\Http\Controllers\SuperAdmin\ChatbotKnowledgeController;
use App\Http\Controllers\SuperAdmin\NewsPostController;

Route::prefix('super-admin')
    ->name('superadmin.')
    ->middleware(['auth', 'verified', 'permission.cache.clear', 'role:super_admin', 'role.superadmin.2fa'])
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
        Route::get('plans/{plan}/restaurants', [SubscriptionPlanController::class, 'planRestaurants'])->name('plans.restaurants');

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

        // Banner Management
        Route::get('banners', [BannerController::class, 'index'])->name('banners.index');
        Route::post('banners', [BannerController::class, 'store'])->name('banners.store');
        Route::patch('banners/{banner}', [BannerController::class, 'update'])->name('banners.update');
        Route::delete('banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');

        // Chatbot Knowledge Base Management
        Route::get('chatbot', [ChatbotKnowledgeController::class, 'index'])->name('chatbot.index');
        Route::post('chatbot', [ChatbotKnowledgeController::class, 'store'])->name('chatbot.store');
        Route::put('chatbot/{knowledge}', [ChatbotKnowledgeController::class, 'update'])->name('chatbot.update');
        Route::delete('chatbot/{knowledge}', [ChatbotKnowledgeController::class, 'destroy'])->name('chatbot.destroy');
        Route::post('chatbot/reload-cache', [ChatbotKnowledgeController::class, 'reloadCache'])->name('chatbot.reload-cache');

        // News / Blog Management
        Route::get('news', [NewsPostController::class, 'index'])->name('news.index');
        Route::post('news', [NewsPostController::class, 'store'])->name('news.store');
        Route::put('news/{post}', [NewsPostController::class, 'update'])->name('news.update');
        Route::delete('news/{post}', [NewsPostController::class, 'destroy'])->name('news.destroy');
        Route::patch('news/{post}/publish', [NewsPostController::class, 'togglePublish'])->name('news.publish');
        Route::patch('news/{post}/featured', [NewsPostController::class, 'toggleFeatured'])->name('news.featured');
        Route::get('news/{post}/content', [NewsPostController::class, 'getContent'])->name('news.content');

        // Impersonation
        Route::post('impersonate/{user}', [\App\Http\Controllers\SuperAdmin\ImpersonateController::class, 'start'])->name('impersonate.start');
    });
