<?php

use App\Http\Controllers\SupplierPortal\CatalogController;
use App\Http\Controllers\SupplierPortal\DashboardController;
use App\Http\Controllers\SupplierPortal\PriceHistoryController;
use App\Http\Controllers\SupplierPortal\SupplierAccountController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Supplier Portal Routes
|--------------------------------------------------------------------------
| Cổng giao tiếp B2B dành riêng cho nhà cung cấp (supplier_admin).
| Supplier chỉ thao tác được trên catalog và giá của mình,
| không can thiệp vào logic bán hàng nội bộ của nhà hàng.
*/

Route::prefix('supplier-portal')
    ->name('supplier-portal.')
    ->middleware(['auth', 'verified', 'supplier.portal'])
    ->group(function (): void {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        // Quản lý danh mục nguyên vật liệu cung ứng
        Route::prefix('catalog')->name('catalog.')->group(function (): void {
            Route::get('/', [CatalogController::class, 'index'])->name('index');
            Route::post('/', [CatalogController::class, 'store'])->name('store');
            Route::patch('/{item}', [CatalogController::class, 'update'])->name('update');
            Route::delete('/{item}', [CatalogController::class, 'destroy'])->name('destroy');
            Route::patch('/{item}/toggle-status', [CatalogController::class, 'toggleStatus'])->name('toggle-status');

            // Cập nhật giá → tự động tạo price history
            Route::post('/{item}/update-price', [CatalogController::class, 'updatePrice'])->name('update-price');
        });

        // Lịch sử biến động giá
        Route::get('/price-history', [PriceHistoryController::class, 'index'])
            ->name('price-history.index');
    });

/*
|--------------------------------------------------------------------------
| Supplier Account Management (dành cho owner/manager của nhà hàng)
|--------------------------------------------------------------------------
| Owner tạo / quản lý tài khoản supplier_admin cho từng nhà cung cấp.
*/
Route::prefix('suppliers/{supplier}/account')
    ->name('suppliers.account.')
    ->middleware(['auth', 'verified', 'tenant.subscription', 'permission:manage_restaurant_settings'])
    ->group(function (): void {
        Route::post('/', [SupplierAccountController::class, 'store'])->name('store');
        Route::delete('/', [SupplierAccountController::class, 'destroy'])->name('destroy');
        Route::post('/reset-password', [SupplierAccountController::class, 'resetPassword'])->name('reset-password');
    });
