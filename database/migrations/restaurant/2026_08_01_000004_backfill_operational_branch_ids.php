<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultBranchForRestaurant = static fn (?int $restaurantId): ?int => $restaurantId
            ? DB::table('restaurant_branches')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->orderBy('id')
                ->value('id')
            : null;

        if (Schema::hasTable('salaries') && Schema::hasColumn('salaries', 'branch_id')) {
            DB::table('salaries')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->get(['id', 'restaurant_id', 'employee_id'])
                ->each(function ($salary) use ($defaultBranchForRestaurant): void {
                    $branchId = DB::table('employees')->where('id', $salary->employee_id)->value('branch_id');
                    $branchId ??= $defaultBranchForRestaurant((int) $salary->restaurant_id);
                    if ($branchId !== null) {
                        DB::table('salaries')->where('id', $salary->id)->update(['branch_id' => $branchId]);
                    }
                });
        }

        if (Schema::hasTable('shift_closings') && Schema::hasColumn('shift_closings', 'branch_id')) {
            DB::table('shift_closings')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->get(['id', 'restaurant_id', 'shift_id', 'cash_register_id', 'cashier_user_id'])
                ->each(function ($closing) use ($defaultBranchForRestaurant): void {
                    $branchId = $closing->cash_register_id
                        ? DB::table('cash_registers')->where('id', $closing->cash_register_id)->value('branch_id')
                        : null;
                    $branchId ??= DB::table('work_shifts')->where('id', $closing->shift_id)->value('branch_id');
                    $branchId ??= DB::table('employees')->where('user_id', $closing->cashier_user_id)->value('branch_id');
                    $branchId ??= $defaultBranchForRestaurant((int) $closing->restaurant_id);

                    if ($branchId !== null) {
                        DB::table('shift_closings')->where('id', $closing->id)->update(['branch_id' => $branchId]);
                    }
                });
        }

        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'branch_id')) {
            DB::table('purchase_orders')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->get(['id', 'restaurant_id'])
                ->each(function ($purchaseOrder) use ($defaultBranchForRestaurant): void {
                    $branchId = DB::table('purchase_order_items')
                        ->join('ingredients', 'purchase_order_items.ingredient_id', '=', 'ingredients.id')
                        ->where('purchase_order_items.purchase_order_id', $purchaseOrder->id)
                        ->value('ingredients.branch_id');
                    $branchId ??= $defaultBranchForRestaurant((int) $purchaseOrder->restaurant_id);

                    if ($branchId !== null) {
                        DB::table('purchase_orders')->where('id', $purchaseOrder->id)->update(['branch_id' => $branchId]);
                    }
                });
        }

        if (Schema::hasTable('inventories') && Schema::hasColumn('inventories', 'branch_id')) {
            DB::table('inventories')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->get(['id', 'restaurant_id', 'ingredient_id'])
                ->each(function ($inventory) use ($defaultBranchForRestaurant): void {
                    $branchId = DB::table('ingredients')->where('id', $inventory->ingredient_id)->value('branch_id');
                    $branchId ??= $defaultBranchForRestaurant((int) $inventory->restaurant_id);
                    if ($branchId !== null) {
                        DB::table('inventories')->where('id', $inventory->id)->update(['branch_id' => $branchId]);
                    }
                });
        }

        if (Schema::hasTable('inventory_transactions') && Schema::hasColumn('inventory_transactions', 'branch_id')) {
            DB::table('inventory_transactions')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->get(['id', 'restaurant_id', 'inventory_id', 'ingredient_id'])
                ->each(function ($transaction) use ($defaultBranchForRestaurant): void {
                    $branchId = $transaction->inventory_id
                        ? DB::table('inventories')->where('id', $transaction->inventory_id)->value('branch_id')
                        : DB::table('ingredients')->where('id', $transaction->ingredient_id)->value('branch_id');
                    $branchId ??= $defaultBranchForRestaurant((int) $transaction->restaurant_id);
                    if ($branchId !== null) {
                        DB::table('inventory_transactions')->where('id', $transaction->id)->update(['branch_id' => $branchId]);
                    }
                });
        }

        if (Schema::hasTable('cash_registers') && Schema::hasColumn('cash_registers', 'branch_id')) {
            DB::table('cash_registers')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->get(['id', 'restaurant_id', 'cashier_user_id'])
                ->each(function ($register) use ($defaultBranchForRestaurant): void {
                    $branchId = DB::table('employees')->where('user_id', $register->cashier_user_id)->value('branch_id');
                    $branchId ??= $defaultBranchForRestaurant((int) $register->restaurant_id);
                    if ($branchId !== null) {
                        DB::table('cash_registers')->where('id', $register->id)->update(['branch_id' => $branchId]);
                    }
                });
        }

        if (Schema::hasTable('cash_transactions') && Schema::hasColumn('cash_transactions', 'branch_id')) {
            DB::table('cash_transactions')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->get(['id', 'restaurant_id', 'cash_register_id'])
                ->each(function ($transaction) use ($defaultBranchForRestaurant): void {
                    $branchId = DB::table('cash_registers')->where('id', $transaction->cash_register_id)->value('branch_id');
                    $branchId ??= $defaultBranchForRestaurant((int) $transaction->restaurant_id);
                    if ($branchId !== null) {
                        DB::table('cash_transactions')->where('id', $transaction->id)->update(['branch_id' => $branchId]);
                    }
                });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'branch_id')) {
            DB::table('payments')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->get(['id', 'restaurant_id', 'order_id'])
                ->each(function ($payment) use ($defaultBranchForRestaurant): void {
                    $branchId = DB::table('orders')->where('id', $payment->order_id)->value('branch_id');
                    $branchId ??= $defaultBranchForRestaurant((int) $payment->restaurant_id);
                    if ($branchId !== null) {
                        DB::table('payments')->where('id', $payment->id)->update(['branch_id' => $branchId]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Backfill migration: dữ liệu đã chuẩn hóa không được hoàn tác về NULL.
    }
};
