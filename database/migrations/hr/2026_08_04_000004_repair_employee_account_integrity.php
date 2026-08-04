<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair legacy employee rows without deleting personnel records.
     * The linked User remains the source of truth for login status and role.
     */
    public function up(): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasTable('users')) {
            return;
        }

        DB::table('employees')
            ->select(['id', 'user_id', 'restaurant_id', 'status', 'email', 'branch_id', 'role_id'])
            ->orderBy('id')
            ->chunkById(200, function ($employees): void {
                foreach ($employees as $employee) {
                    $user = $employee->user_id
                        ? DB::table('users')
                            ->where('id', $employee->user_id)
                            ->first(['id', 'email', 'status', 'branch_id'])
                        : null;

                    if (! $user) {
                        // Keep the HR record for audit/history, but it must not look
                        // like an active account when no login account is linked.
                        if ($employee->status === 'active') {
                            DB::table('employees')
                                ->where('id', $employee->id)
                                ->update(['status' => 'inactive']);
                        }

                        continue;
                    }

                    $employeeUpdates = [];
                    if (blank($employee->email) && filled($user->email)) {
                        $employeeUpdates['email'] = $user->email;
                    }

                    if (
                        $employee->branch_id === null
                        && $user->branch_id !== null
                        && Schema::hasTable('restaurant_branches')
                        && DB::table('restaurant_branches')
                            ->where('id', $user->branch_id)
                            ->where('restaurant_id', $employee->restaurant_id)
                            ->exists()
                    ) {
                        $employeeUpdates['branch_id'] = $user->branch_id;
                    }

                    $userRole = $this->userRole($user->id);
                    if ($employee->role_id === null && $userRole) {
                        $employeeUpdates['role_id'] = $userRole->role_id;
                    }

                    // An inactive login must never be represented as an active
                    // employee, and a terminated employee must not retain access.
                    if ($employee->status === 'terminated') {
                        if ($user->status === 'active') {
                            $this->disableUser((int) $user->id);
                        }
                    } elseif ($employee->status !== 'active' || $user->status !== 'active') {
                        if ($employee->status !== 'inactive') {
                            $employeeUpdates['status'] = 'inactive';
                        }
                        if ($user->status !== 'inactive') {
                            $this->disableUser((int) $user->id);
                        }
                    }

                    if ($employeeUpdates !== []) {
                        DB::table('employees')
                            ->where('id', $employee->id)
                            ->update($employeeUpdates);
                    }

                    // Employee::saved() normally maintains this pivot, but legacy
                    // rows were often written directly and missed it.
                    if (
                        isset($employeeUpdates['role_id'])
                        && Schema::hasTable('model_has_roles')
                    ) {
                        DB::table('model_has_roles')->insertOrIgnore([
                            'role_id' => $employeeUpdates['role_id'],
                            'model_type' => 'App\\Models\\Employee',
                            'model_id' => $employee->id,
                        ]);
                    }
                }
            });
    }

    private function userRole(int $userId): ?object
    {
        if (! Schema::hasTable('model_has_roles')) {
            return null;
        }

        return DB::table('model_has_roles')
            ->where('model_type', 'App\\Models\\User')
            ->where('model_id', $userId)
            ->orderBy('role_id')
            ->first(['role_id']);
    }

    private function disableUser(int $userId): void
    {
        $updates = ['status' => 'inactive'];
        if (Schema::hasColumn('users', 'security_session_version')) {
            $updates['security_session_version'] = DB::raw('security_session_version + 1');
        }

        DB::table('users')->where('id', $userId)->update($updates);
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversed.
    }
};
