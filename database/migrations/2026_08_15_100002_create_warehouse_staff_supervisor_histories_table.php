<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('warehouse_staff_supervisor_histories');
        Schema::create('warehouse_staff_supervisor_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('warehouse_branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('warehouse_staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->string('status', 20)->default('active'); // active, ended
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'warehouse_staff_id'], 'wssh_rid_wsid_idx');
            $table->index(['supervisor_user_id', 'status'], 'wssh_sup_stat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_staff_supervisor_histories');
    }
};
