<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('restaurant_id')->nullable()->after('id');
            $table->unsignedBigInteger('branch_id')->nullable()->after('restaurant_id');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar_url', 2048)->nullable()->after('phone');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('avatar_url');
            $table->timestamp('last_login_at')->nullable()->after('status');

            $table->index(['restaurant_id', 'status'], 'users_restaurant_status_index');
            $table->index('branch_id', 'users_branch_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_restaurant_status_index');
            $table->dropIndex('users_branch_index');
            $table->dropColumn([
                'restaurant_id',
                'branch_id',
                'phone',
                'avatar_url',
                'status',
                'last_login_at',
            ]);
        });
    }
};
