<?php

use App\Http\Controllers\SuperAdmin\AccountController;
use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\BannerController;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Http\Controllers\SuperAdmin\BillingOverrideController;
use App\Http\Controllers\SuperAdmin\ChatbotKnowledgeController;
use App\Http\Controllers\SuperAdmin\CouponController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\NewsPostController;
use App\Http\Controllers\SuperAdmin\RestaurantController;
use App\Http\Controllers\SuperAdmin\SubscriptionPlanController;
use App\Http\Controllers\SuperAdmin\SupportPortalController;
use App\Http\Controllers\SuperAdmin\CustomPlanBuilderController;
use App\Http\Controllers\SuperAdmin\ChurnController;


Route::prefix('super-admin')
    ->name('superadmin.')
    ->middleware(['auth', 'verified', 'permission.cache.clear', 'role:super_admin', 'role.superadmin.2fa'])
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/segments/{segment}', [DashboardController::class, 'segmentRestaurants'])->name('dashboard.segments');
        Route::get('dashboard/export/csv', [DashboardController::class, 'exportCsv'])->name('dashboard.export.csv');
        Route::get('dashboard/export/report', [DashboardController::class, 'exportReport'])->name('dashboard.export.report');
        Route::post('dashboard/report-subscription', [DashboardController::class, 'updateReportSubscription'])->name('dashboard.report-subscription.update');
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('billing/analytics', [BillingController::class, 'analytics'])->name('billing.analytics');
        Route::get('billing/export', [BillingController::class, 'exportCsv'])->name('billing.export');
        Route::get('billing/invoices/{invoice}/download', [BillingController::class, 'downloadInvoice'])->name('billing.invoices.download');
        Route::post('billing/invoices/{invoice}/resend', [BillingController::class, 'resendInvoice'])->name('billing.invoices.resend');
        Route::post('billing/invoices/{invoice}/regenerate', [BillingController::class, 'regenerateInvoice'])->name('billing.invoices.regenerate');
        Route::post('billing/webhooks/{webhook}/retry', [BillingController::class, 'retryWebhook'])->name('billing.webhooks.retry');

        Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::patch('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
        Route::patch('coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('coupons.toggle');


        Route::get('restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
        Route::post('restaurants', [RestaurantController::class, 'store'])->name('restaurants.store');
        Route::get('restaurants/{restaurant}', [RestaurantController::class, 'show'])->name('restaurants.show');
        Route::get('restaurants/{restaurant}/subscriptions-history', [RestaurantController::class, 'subscriptionsHistory'])->name('restaurants.subscriptions-history');
        Route::patch('restaurants/{restaurant}/status', [RestaurantController::class, 'updateStatus'])->name('restaurants.status');
        Route::patch('restaurants/{restaurant}/plan', [RestaurantController::class, 'updatePlan'])->name('restaurants.plan');
        Route::patch('restaurants/{restaurant}/unflag', [RestaurantController::class, 'unflag'])->name('restaurants.unflag');
        Route::patch('restaurants/{restaurant}/storage-quota', [RestaurantController::class, 'updateStorageQuota'])->name('restaurants.storage-quota');
        Route::post('restaurants/{restaurant}/billing-overrides', [BillingOverrideController::class, 'store'])->name('restaurants.billing-overrides.store');
        Route::post('restaurants/{restaurant}/custom-plan', [CustomPlanBuilderController::class, 'store'])->name('restaurants.custom-plan.store');

        // Garbage Collector UI
        Route::get('garbage-collector', [\App\Http\Controllers\SuperAdmin\GarbageCollectorController::class, 'index'])->name('garbage-collector.index');
        Route::post('garbage-collector/cleanup', [\App\Http\Controllers\SuperAdmin\GarbageCollectorController::class, 'cleanup'])->name('garbage-collector.cleanup');

        Route::get('plans', [SubscriptionPlanController::class, 'index'])->name('plans.index');
        Route::post('plans', [SubscriptionPlanController::class, 'store'])->name('plans.store');
        Route::patch('plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('plans.update');
        Route::get('plans/{plan}/restaurants', [SubscriptionPlanController::class, 'planRestaurants'])->name('plans.restaurants');

        Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::post('accounts/{user}/reset-password', [AccountController::class, 'resetPassword'])->name('accounts.reset-password');
        Route::post('accounts/{user}/disable-2fa', [AccountController::class, 'disable2FA'])->name('accounts.disable-2fa');
        Route::patch('accounts/{user}/status', [AccountController::class, 'toggleStatus'])->name('accounts.status');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('support', [SupportPortalController::class, 'index'])->name('support.index');
        Route::post('support/tickets/bulk', [SupportPortalController::class, 'bulkUpdateTickets'])->name('support.tickets.bulk');
        Route::post('support/tickets', [SupportPortalController::class, 'storeTicket'])->name('support.tickets.store');
        Route::patch('support/tickets/{ticket}', [SupportPortalController::class, 'updateTicket'])->name('support.tickets.update');
        Route::delete('support/tickets/{ticket}', [SupportPortalController::class, 'destroyTicket'])->name('support.tickets.destroy');
        Route::post('support/tickets/{ticket}/replies', [SupportPortalController::class, 'storeReply'])->name('support.tickets.replies.store');
        Route::patch('support/tickets/{ticket}/replies/{reply}', [SupportPortalController::class, 'updateReply'])->name('support.tickets.replies.update');
        Route::delete('support/tickets/{ticket}/replies/{reply}', [SupportPortalController::class, 'destroyReply'])->name('support.tickets.replies.destroy');
        Route::post('support/announcements', [SupportPortalController::class, 'storeAnnouncement'])->name('support.announcements.store');
        Route::patch('support/announcements/{announcement}/unpublish', [SupportPortalController::class, 'unpublishAnnouncement'])->name('support.announcements.unpublish');
        Route::get('support/export', [SupportPortalController::class, 'exportCsv'])->name('support.export');
        Route::post('support/articles', [SupportPortalController::class, 'storeArticle'])->name('support.articles.store');
        Route::post('support/rules', [SupportPortalController::class, 'storeRule'])->name('support.rules.store');
        Route::patch('support/rules/{rule}', [SupportPortalController::class, 'updateRule'])->name('support.rules.update');
        Route::delete('support/rules/{rule}', [SupportPortalController::class, 'destroyRule'])->name('support.rules.destroy');
        Route::patch('support/rules/{rule}/toggle', [SupportPortalController::class, 'toggleRule'])->name('support.rules.toggle');
        Route::post('support/alerts/run', [SupportPortalController::class, 'runAlertCheck'])->name('support.alerts.run');

        // Banner Management
        Route::get('banners', [BannerController::class, 'index'])->name('banners.index');
        Route::post('banners', [BannerController::class, 'store'])->name('banners.store');
        Route::post('banners/reorder', [BannerController::class, 'reorder'])->name('banners.reorder');
        Route::patch('banners/{banner}', [BannerController::class, 'update'])->name('banners.update');
        Route::delete('banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');

        // Chatbot Knowledge Base Management
        Route::get('chatbot', [ChatbotKnowledgeController::class, 'index'])->name('chatbot.index');
        Route::post('chatbot', [ChatbotKnowledgeController::class, 'store'])->name('chatbot.store');
        Route::put('chatbot/{knowledge}', [ChatbotKnowledgeController::class, 'update'])->name('chatbot.update');
        Route::delete('chatbot/{knowledge}', [ChatbotKnowledgeController::class, 'destroy'])->name('chatbot.destroy');
        Route::post('chatbot/reload-cache', [ChatbotKnowledgeController::class, 'reloadCache'])->name('chatbot.reload-cache');

        // Chatbot Diagnostics & Retraining
        Route::get('chatbot-diagnostics', [\App\Http\Controllers\SuperAdmin\ChatbotDiagnosticsController::class, 'index'])->name('chatbot-diagnostics.index');
        Route::post('chatbot-diagnostics/retrain', [\App\Http\Controllers\SuperAdmin\ChatbotDiagnosticsController::class, 'retrain'])->name('chatbot-diagnostics.retrain');
        Route::post('chatbot-diagnostics/test-query', [\App\Http\Controllers\SuperAdmin\ChatbotDiagnosticsController::class, 'testQuery'])->name('chatbot-diagnostics.test-query');
        Route::post('chatbot-diagnostics/{query}/resolve', [\App\Http\Controllers\SuperAdmin\ChatbotDiagnosticsController::class, 'markResolved'])->name('chatbot-diagnostics.resolve');
        Route::post('chatbot-diagnostics/{query}/unresolve', [\App\Http\Controllers\SuperAdmin\ChatbotDiagnosticsController::class, 'markUnresolved'])->name('chatbot-diagnostics.unresolve');
        Route::post('chatbot-diagnostics/bulk-resolve', [\App\Http\Controllers\SuperAdmin\ChatbotDiagnosticsController::class, 'bulkResolve'])->name('chatbot-diagnostics.bulk-resolve');
        Route::delete('chatbot-diagnostics/delete-resolved', [\App\Http\Controllers\SuperAdmin\ChatbotDiagnosticsController::class, 'deleteResolved'])->name('chatbot-diagnostics.delete-resolved');


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

        // Referral and Payout Management
        Route::get('referrals', [\App\Http\Controllers\SuperAdmin\ReferralController::class, 'index'])->name('referrals.index');
        Route::post('referrals/settings', [\App\Http\Controllers\SuperAdmin\ReferralController::class, 'updateSettings'])->name('referrals.settings.update');
        Route::post('referrals/withdrawals/{withdrawal}/approve', [\App\Http\Controllers\SuperAdmin\ReferralController::class, 'approveWithdrawal'])->name('referrals.withdrawals.approve');
        Route::post('referrals/withdrawals/{withdrawal}/reject', [\App\Http\Controllers\SuperAdmin\ReferralController::class, 'rejectWithdrawal'])->name('referrals.withdrawals.reject');

        // Service Monitor & Maintenance Mode
        Route::get('service-monitor', [\App\Http\Controllers\SuperAdmin\ServiceMonitorController::class, 'index'])->name('service-monitor.index');
        Route::post('service-monitor/ping', [\App\Http\Controllers\SuperAdmin\ServiceMonitorController::class, 'pingAll'])->name('service-monitor.ping');
        Route::post('service-monitor/{service}/toggle-maintenance', [\App\Http\Controllers\SuperAdmin\ServiceMonitorController::class, 'toggleMaintenance'])->name('service-monitor.toggle-maintenance');
        Route::post('service-monitor/{service}/update-message', [\App\Http\Controllers\SuperAdmin\ServiceMonitorController::class, 'updateMessage'])->name('service-monitor.update-message');

        // Database Backup & Periodic Optimization
        Route::get('backup-maintenance', [\App\Http\Controllers\SuperAdmin\BackupMaintenanceController::class, 'index'])->name('backup-maintenance.index');
        Route::post('backup-maintenance/backup', [\App\Http\Controllers\SuperAdmin\BackupMaintenanceController::class, 'backup'])->name('backup-maintenance.backup');
        Route::post('backup-maintenance/optimize', [\App\Http\Controllers\SuperAdmin\BackupMaintenanceController::class, 'optimize'])->name('backup-maintenance.optimize');
        Route::get('backup-maintenance/download/{filename}', [\App\Http\Controllers\SuperAdmin\BackupMaintenanceController::class, 'download'])->name('backup-maintenance.download');
        Route::delete('backup-maintenance/delete/{filename}', [\App\Http\Controllers\SuperAdmin\BackupMaintenanceController::class, 'delete'])->name('backup-maintenance.delete');

        // Global Announcement & Notification Campaigns
        Route::get('campaigns', [\App\Http\Controllers\SuperAdmin\NotificationCampaignController::class, 'index'])->name('campaigns.index');
        Route::post('campaigns', [\App\Http\Controllers\SuperAdmin\NotificationCampaignController::class, 'store'])->name('campaigns.store');
        Route::post('campaigns/preview-audience', [\App\Http\Controllers\SuperAdmin\NotificationCampaignController::class, 'previewAudience'])->name('campaigns.preview');
        Route::delete('campaigns/{campaign}', [\App\Http\Controllers\SuperAdmin\NotificationCampaignController::class, 'destroy'])->name('campaigns.destroy');
        Route::post('campaigns/{campaign}/send', [\App\Http\Controllers\SuperAdmin\NotificationCampaignController::class, 'send'])->name('campaigns.send');

        // Global System Settings
        Route::get('settings', [\App\Http\Controllers\SuperAdmin\SystemSettingController::class, 'index'])->name('settings.index');
        Route::post('settings', [\App\Http\Controllers\SuperAdmin\SystemSettingController::class, 'update'])->name('settings.update');

        // Churn Prediction & Customer Success Dashboard
        Route::get('churn-prediction', [ChurnController::class, 'index'])->name('churn.index');
        Route::post('churn-prediction/recalculate', [ChurnController::class, 'recalculate'])->name('churn.recalculate');
        Route::post('churn-prediction/trigger-email/{restaurant}', [ChurnController::class, 'triggerEmail'])->name('churn.trigger-email');
    });

