<?php

use App\Http\Controllers\SuperAdmin\AccountController;
use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\BackupMaintenanceController;
use App\Http\Controllers\SuperAdmin\BannerController;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Http\Controllers\SuperAdmin\BillingOverrideController;
use App\Http\Controllers\SuperAdmin\ChatbotDiagnosticsController;
use App\Http\Controllers\SuperAdmin\ChatbotKnowledgeController;
use App\Http\Controllers\SuperAdmin\ChurnController;
use App\Http\Controllers\SuperAdmin\CouponController;
use App\Http\Controllers\SuperAdmin\CustomPlanBuilderController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\GarbageCollectorController;
use App\Http\Controllers\SuperAdmin\GlobalSearchController;
use App\Http\Controllers\SuperAdmin\ImpersonateController;
use App\Http\Controllers\SuperAdmin\MaintenanceScheduleController;
use App\Http\Controllers\SuperAdmin\MeilisearchConsoleController;
use App\Http\Controllers\SuperAdmin\NewsPostController;
use App\Http\Controllers\SuperAdmin\NotificationCampaignController;
use App\Http\Controllers\SuperAdmin\ReferralController;
use App\Http\Controllers\SuperAdmin\RestaurantController;
use App\Http\Controllers\SuperAdmin\RestaurantCrmController;
use App\Http\Controllers\SuperAdmin\ServiceMonitorController;
use App\Http\Controllers\SuperAdmin\SubscriptionPlanController;
use App\Http\Controllers\SuperAdmin\SupportPortalController;
use App\Http\Controllers\SuperAdmin\SystemSettingController;

Route::prefix('super-admin')
    ->name('superadmin.')
    ->middleware(['auth', 'verified', 'permission.cache.clear', 'superadmin.access', 'role.superadmin.2fa'])
    ->group(function () {

        // ── Shared: all SuperAdmin sub-roles ────────────────────────────────
        Route::middleware('superadmin.permission:superadmin.dashboard.view')->group(function () {
            Route::get('global-search', [GlobalSearchController::class, 'search'])->name('global-search');

            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('dashboard/segments/{segment}', [DashboardController::class, 'segmentRestaurants'])->name('dashboard.segments');
            Route::get('dashboard/export/csv', [DashboardController::class, 'exportCsv'])->name('dashboard.export.csv');
            Route::get('dashboard/export/report', [DashboardController::class, 'exportReport'])->name('dashboard.export.report');
            Route::post('dashboard/report-subscription', [DashboardController::class, 'updateReportSubscription'])->name('dashboard.report-subscription.update');

            Route::get('restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
            Route::post('restaurants', [RestaurantController::class, 'store'])->name('restaurants.store');
            Route::get('restaurants/{restaurant}', [RestaurantController::class, 'show'])->name('restaurants.show');
            Route::get('restaurants/{restaurant}/subscriptions-history', [RestaurantController::class, 'subscriptionsHistory'])->name('restaurants.subscriptions-history');
            Route::patch('restaurants/{restaurant}/status', [RestaurantController::class, 'updateStatus'])->name('restaurants.status');
            Route::patch('restaurants/{restaurant}/plan', [RestaurantController::class, 'updatePlan'])->name('restaurants.plan');
            Route::patch('restaurants/{restaurant}/unflag', [RestaurantController::class, 'unflag'])->name('restaurants.unflag');
            Route::patch('restaurants/{restaurant}/storage-quota', [RestaurantController::class, 'updateStorageQuota'])->name('restaurants.storage-quota');

            Route::post('restaurants/{restaurant}/notes', [RestaurantCrmController::class, 'storeNote'])->name('restaurants.notes.store');
            Route::delete('restaurants/{restaurant}/notes/{note}', [RestaurantCrmController::class, 'destroyNote'])->name('restaurants.notes.destroy');
            Route::post('restaurants/{restaurant}/tags', [RestaurantCrmController::class, 'storeTag'])->name('restaurants.tags.store');
            Route::delete('restaurants/{restaurant}/tags/{tag}', [RestaurantCrmController::class, 'destroyTag'])->name('restaurants.tags.destroy');
            Route::post('restaurants/{restaurant}/followups', [RestaurantCrmController::class, 'storeFollowup'])->name('restaurants.followups.store');
            Route::patch('restaurants/{restaurant}/followups/{followup}/complete', [RestaurantCrmController::class, 'completeFollowup'])->name('restaurants.followups.complete');

            Route::get('churn-prediction', [ChurnController::class, 'index'])->name('churn.index');
            Route::post('churn-prediction/recalculate', [ChurnController::class, 'recalculate'])->name('churn.recalculate');
            Route::post('churn-prediction/trigger-email/{restaurant}', [ChurnController::class, 'triggerEmail'])->name('churn.trigger-email');

            Route::get('banners', [BannerController::class, 'index'])->name('banners.index');
            Route::post('banners', [BannerController::class, 'store'])->name('banners.store');
            Route::post('banners/reorder', [BannerController::class, 'reorder'])->name('banners.reorder');
            Route::patch('banners/{banner}', [BannerController::class, 'update'])->name('banners.update');
            Route::delete('banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');

            Route::get('news', [NewsPostController::class, 'index'])->name('news.index');
            Route::post('news', [NewsPostController::class, 'store'])->name('news.store');
            Route::put('news/{post}', [NewsPostController::class, 'update'])->name('news.update');
            Route::delete('news/{post}', [NewsPostController::class, 'destroy'])->name('news.destroy');
            Route::patch('news/{post}/publish', [NewsPostController::class, 'togglePublish'])->name('news.publish');
            Route::patch('news/{post}/featured', [NewsPostController::class, 'toggleFeatured'])->name('news.featured');
            Route::get('news/{post}/content', [NewsPostController::class, 'getContent'])->name('news.content');
        });

        // ── Billing Admin ───────────────────────────────────────────────────
        Route::middleware('superadmin.permission:superadmin.billing.manage')->group(function () {
            Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
            Route::get('billing/analytics', [BillingController::class, 'analytics'])->name('billing.analytics');
            Route::get('billing/export', [BillingController::class, 'exportCsv'])->name('billing.export');
            Route::get('billing/invoices/{invoice}/download', [BillingController::class, 'downloadInvoice'])->name('billing.invoices.download');
            Route::post('billing/invoices/{invoice}/resend', [BillingController::class, 'resendInvoice'])->name('billing.invoices.resend');
            Route::post('billing/invoices/{invoice}/regenerate', [BillingController::class, 'regenerateInvoice'])->name('billing.invoices.regenerate');
            Route::patch('billing/invoices/{invoice}/write-off', [BillingController::class, 'writeOffInvoice'])->name('billing.invoices.write-off');
            Route::post('billing/webhooks/{webhook}/retry', [BillingController::class, 'retryWebhook'])->name('billing.webhooks.retry');
            Route::get('billing/ledger', [BillingController::class, 'ledger'])->name('billing.ledger');
            Route::get('billing/ledger/export', [BillingController::class, 'ledgerExport'])->name('billing.ledger.export');
            Route::get('billing/revenue-recognition', [BillingController::class, 'revenueRecognition'])->name('billing.revenue-recognition');
            Route::get('billing/dunning', [BillingController::class, 'dunning'])->name('billing.dunning');
            Route::post('billing/dunning/{subscription}/send', [BillingController::class, 'sendDunning'])->name('billing.dunning.send');
            Route::post('billing/dunning/{subscription}/pause', [BillingController::class, 'pauseDunning'])->name('billing.dunning.pause');
            Route::get('billing/lifecycle', [BillingController::class, 'lifecycle'])->name('billing.lifecycle');

            Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
            Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
            Route::patch('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
            Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
            Route::patch('coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('coupons.toggle');

            Route::get('plans', [SubscriptionPlanController::class, 'index'])->name('plans.index');
            Route::post('plans', [SubscriptionPlanController::class, 'store'])->name('plans.store');
            Route::patch('plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('plans.update');
            Route::get('plans/{plan}/restaurants', [SubscriptionPlanController::class, 'planRestaurants'])->name('plans.restaurants');

            Route::post('restaurants/{restaurant}/billing-overrides', [BillingOverrideController::class, 'store'])->name('restaurants.billing-overrides.store');
            Route::post('restaurants/{restaurant}/custom-plan', [CustomPlanBuilderController::class, 'store'])->name('restaurants.custom-plan.store');

            Route::get('referrals', [ReferralController::class, 'index'])->name('referrals.index');
            Route::post('referrals/settings', [ReferralController::class, 'updateSettings'])->name('referrals.settings.update');
            Route::post('referrals/withdrawals/{withdrawal}/approve', [ReferralController::class, 'approveWithdrawal'])->name('referrals.withdrawals.approve');
            Route::post('referrals/withdrawals/{withdrawal}/reject', [ReferralController::class, 'rejectWithdrawal'])->name('referrals.withdrawals.reject');
        });

        // ── Support Specialist ──────────────────────────────────────────────
        Route::middleware('superadmin.permission:superadmin.support.manage')->group(function () {
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
            Route::post('support/tickets/{ticket}/escalate', [SupportPortalController::class, 'escalateTicket'])->name('support.tickets.escalate');
            Route::post('support/sla/recalculate', [SupportPortalController::class, 'recalculateSla'])->name('support.sla.recalculate');

            Route::get('chatbot', [ChatbotKnowledgeController::class, 'index'])->name('chatbot.index');
            Route::post('chatbot', [ChatbotKnowledgeController::class, 'store'])->name('chatbot.store');
            Route::put('chatbot/{knowledge}', [ChatbotKnowledgeController::class, 'update'])->name('chatbot.update');
            Route::delete('chatbot/{knowledge}', [ChatbotKnowledgeController::class, 'destroy'])->name('chatbot.destroy');
            Route::post('chatbot/reload-cache', [ChatbotKnowledgeController::class, 'reloadCache'])->name('chatbot.reload-cache');

            Route::get('chatbot-diagnostics', [ChatbotDiagnosticsController::class, 'index'])->name('chatbot-diagnostics.index');
            Route::post('chatbot-diagnostics/retrain', [ChatbotDiagnosticsController::class, 'retrain'])->name('chatbot-diagnostics.retrain');
            Route::post('chatbot-diagnostics/test-query', [ChatbotDiagnosticsController::class, 'testQuery'])->name('chatbot-diagnostics.test-query');
            Route::post('chatbot-diagnostics/{query}/resolve', [ChatbotDiagnosticsController::class, 'markResolved'])->name('chatbot-diagnostics.resolve');
            Route::post('chatbot-diagnostics/{query}/unresolve', [ChatbotDiagnosticsController::class, 'markUnresolved'])->name('chatbot-diagnostics.unresolve');
            Route::post('chatbot-diagnostics/bulk-resolve', [ChatbotDiagnosticsController::class, 'bulkResolve'])->name('chatbot-diagnostics.bulk-resolve');
            Route::delete('chatbot-diagnostics/delete-resolved', [ChatbotDiagnosticsController::class, 'deleteResolved'])->name('chatbot-diagnostics.delete-resolved');

            Route::get('campaigns', [NotificationCampaignController::class, 'index'])->name('campaigns.index');
            Route::post('campaigns', [NotificationCampaignController::class, 'store'])->name('campaigns.store');
            Route::post('campaigns/preview-audience', [NotificationCampaignController::class, 'previewAudience'])->name('campaigns.preview');
            Route::delete('campaigns/{campaign}', [NotificationCampaignController::class, 'destroy'])->name('campaigns.destroy');
            Route::post('campaigns/{campaign}/send', [NotificationCampaignController::class, 'send'])->name('campaigns.send');
        });

        // ── System Administrator ────────────────────────────────────────────
        Route::middleware('superadmin.permission:superadmin.system.manage')->group(function () {
            Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
            Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
            Route::post('accounts/{user}/reset-password', [AccountController::class, 'resetPassword'])->name('accounts.reset-password');
            Route::post('accounts/{user}/disable-2fa', [AccountController::class, 'disable2FA'])->name('accounts.disable-2fa');
            Route::patch('accounts/{user}/status', [AccountController::class, 'toggleStatus'])->name('accounts.status');
            Route::patch('accounts/{user}/role', [AccountController::class, 'updateRole'])->name('accounts.update-role');

            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

            Route::get('settings', [SystemSettingController::class, 'index'])->name('settings.index');
            Route::post('settings', [SystemSettingController::class, 'update'])->name('settings.update');
            Route::post('settings/test-email', [SystemSettingController::class, 'testEmail'])->name('settings.test-email');

            Route::get('service-monitor', [ServiceMonitorController::class, 'index'])->name('service-monitor.index');
            Route::post('service-monitor/ping', [ServiceMonitorController::class, 'pingAll'])->name('service-monitor.ping');
            Route::post('service-monitor/{service}/toggle-maintenance', [ServiceMonitorController::class, 'toggleMaintenance'])->name('service-monitor.toggle-maintenance');
            Route::post('service-monitor/{service}/update-message', [ServiceMonitorController::class, 'updateMessage'])->name('service-monitor.update-message');

            Route::get('maintenance-schedules', [MaintenanceScheduleController::class, 'index'])->name('maintenance-schedules.index');
            Route::post('maintenance-schedules', [MaintenanceScheduleController::class, 'store'])->name('maintenance-schedules.store');
            Route::delete('maintenance-schedules/{schedule}', [MaintenanceScheduleController::class, 'destroy'])->name('maintenance-schedules.destroy');

            Route::get('backup-maintenance', [BackupMaintenanceController::class, 'index'])->name('backup-maintenance.index');
            Route::post('backup-maintenance/backup', [BackupMaintenanceController::class, 'backup'])->name('backup-maintenance.backup');
            Route::post('backup-maintenance/optimize', [BackupMaintenanceController::class, 'optimize'])->name('backup-maintenance.optimize');
            Route::get('backup-maintenance/download/{filename}', [BackupMaintenanceController::class, 'download'])->name('backup-maintenance.download');
            Route::delete('backup-maintenance/delete/{filename}', [BackupMaintenanceController::class, 'delete'])->name('backup-maintenance.delete');

            Route::get('garbage-collector', [GarbageCollectorController::class, 'index'])->name('garbage-collector.index');
            Route::post('garbage-collector/cleanup', [GarbageCollectorController::class, 'cleanup'])->name('garbage-collector.cleanup');

            Route::get('meilisearch-console', [MeilisearchConsoleController::class, 'index'])->name('meilisearch-console.index');
            Route::post('meilisearch-console/sync', [MeilisearchConsoleController::class, 'sync'])->name('meilisearch-console.sync');
            Route::post('meilisearch-console/clear-stats', [MeilisearchConsoleController::class, 'clearStats'])->name('meilisearch-console.clear-stats');

            Route::post('impersonate/{user}', [ImpersonateController::class, 'start'])->name('impersonate.start');
        });
    });
