<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\PaymentWebhookController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
// Chatbot API  public (rate limited)
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware('throttle:30,1')->group(function () {
    Route::post('api/chatbot/message', [ChatbotController::class, 'message'])->name('chatbot.message');
    Route::get('api/chatbot/suggestions', [ChatbotController::class, 'suggestions'])->name('chatbot.suggestions');
    Route::post('api/chatbot/feedback', [ChatbotController::class, 'feedback'])->name('chatbot.feedback');
});

Route::post('webhooks/payments', PaymentWebhookController::class)->name('billing.webhook');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('billing/checkout', CheckoutController::class)->name('billing.checkout');
    Route::get('billing/pay/{code}', [CheckoutController::class, 'payPage'])->name('billing.pay');
    Route::get('api/billing/check/{code}', [CheckoutController::class, 'checkStatus']);
});

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tin-tuc', [NewsController::class, 'index'])->name('news.index');
Route::get('/tin-tuc/{slug}', [NewsController::class, 'show'])->name('news.show');

use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SupportController;

Route::middleware(['auth', 'verified', 'tenant.subscription'])->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Onboarding API
    Route::post('api/onboarding/update', [OnboardingController::class, 'updateProgress'])->name('onboarding.update');
    Route::post('api/onboarding/reset', [OnboardingController::class, 'resetProgress'])->name('onboarding.reset');

    // Support Portal
    Route::get('support', [SupportController::class, 'index'])->name('support.index');
    Route::post('support/tickets', [SupportController::class, 'storeTicket'])->name('support.tickets.store');
    Route::post('support/tickets/{ticket}/replies', [SupportController::class, 'storeReply'])->name('support.tickets.replies.store');

    // Functional Pages for Guided Tours
    Route::get('products', [SupportController::class, 'productsPage'])->name('products.index');
    Route::post('products', [SupportController::class, 'storeProduct'])->name('products.store');
    Route::patch('products/{product}', [SupportController::class, 'updateProduct'])->name('products.update');
    Route::delete('products/{product}', [SupportController::class, 'destroyProduct'])->name('products.destroy');
    Route::post('product-categories', [SupportController::class, 'storeCategory'])->name('product-categories.store');
    Route::delete('product-categories/{category}', [SupportController::class, 'destroyCategory'])->name('product-categories.destroy');

    Route::get('inventory', [SupportController::class, 'inventoryPage'])->name('inventory.index');
    Route::post('inventory/ingredients', [SupportController::class, 'storeIngredient'])->name('inventory.ingredients.store');
    Route::post('inventory/recipes', [SupportController::class, 'storeRecipe'])->name('inventory.recipes.store');

    Route::get('employees', [SupportController::class, 'employeesPage'])->name('employees.index');
    Route::post('employees', [SupportController::class, 'storeEmployee'])->name('employees.store');
    Route::patch('employees/{employee}', [SupportController::class, 'updateEmployee'])->name('employees.update');

    // Tables management
    Route::get('tables', [\App\Http\Controllers\TablesController::class, 'index'])->name('tables.index');
    Route::post('tables/areas', [\App\Http\Controllers\TablesController::class, 'storeArea'])->name('tables.areas.store');
    Route::post('tables', [\App\Http\Controllers\TablesController::class, 'store'])->name('tables.store');
    Route::patch('tables/{table}', [\App\Http\Controllers\TablesController::class, 'update'])->name('tables.update');
    Route::delete('tables/{table}', [\App\Http\Controllers\TablesController::class, 'destroy'])->name('tables.destroy');

    // Orders management
    Route::get('orders', [\App\Http\Controllers\OrdersController::class, 'index'])->name('orders.index');
    Route::patch('orders/{order}/status', [\App\Http\Controllers\OrdersController::class, 'updateStatus'])->name('orders.update-status');

    // Revenue / Reports
    Route::get('reports', [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');

    // Support booking demo
    Route::post('support/bookings', [SupportController::class, 'storeBooking'])->name('support.bookings.store');
});

require __DIR__.'/settings.php';

