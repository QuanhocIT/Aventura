<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BRANCH_SCOPED_ROLES = [
        'manager',
        'cashier',
        'waiter',
        'kitchen',
        'inventory_staff',
        'staff',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('restaurant_branches')) {
            return;
        }

        $restaurants = DB::table('restaurant_branches')
            ->where('status', 'active')
            ->select('restaurant_id')
            ->groupBy('restaurant_id')
            ->get();

        foreach ($restaurants as $restaurant) {
            $branchId = DB::table('restaurant_branches')
                ->where('restaurant_id', $restaurant->restaurant_id)
                ->where('status', 'active')
                ->orderBy('id')
                ->value('id');

            $branchCount = DB::table('restaurant_branches')
                ->where('restaurant_id', $restaurant->restaurant_id)
                ->where('status', 'active')
                ->count();

            if ($branchId === null || $branchCount !== 1) {
                continue;
            }

            $this->assignEmployees((int) $restaurant->restaurant_id, (int) $branchId);
            $this->assignBranchScopedUsers((int) $restaurant->restaurant_id, (int) $branchId);
        }
    }

    private function assignEmployees(int $restaurantId, int $branchId): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'branch_id')) {
            return;
        }

        DB::table('employees')
            ->where('restaurant_id', $restaurantId)
            ->whereNull('branch_id')
            ->update(['branch_id' => $branchId]);
    }

    private function assignBranchScopedUsers(int $restaurantId, int $branchId): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'branch_id')
            || ! Schema::hasTable('model_has_roles')
            || ! Schema::hasTable('roles')
        ) {
            return;
        }

        DB::table('users as user')
            ->join('model_has_roles as user_role', function ($join): void {
                $join->on('user_role.model_id', '=', 'user.id')
                    ->where('user_role.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'user_role.role_id')
            ->where('user.restaurant_id', $restaurantId)
            ->whereNull('user.branch_id')
            ->whereIn('roles.name', self::BRANCH_SCOPED_ROLES)
            ->update(['user.branch_id' => $branchId]);
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversed.
    }
};
