<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_inspection_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->string('plan_code');
            $table->string('title');
            $table->string('inspection_type')->default('routine'); // routine, thematic, surprise, follow_up
            $table->date('scheduled_date');
            $table->date('due_date')->nullable();
            $table->foreignId('lead_inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('planned'); // planned, in_progress, completed, cancelled
            $table->text('scope')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['restaurant_id', 'status', 'scheduled_date'], 'oip_restaurant_status_date_idx');
            $table->index(['restaurant_id', 'branch_id'], 'oip_restaurant_branch_idx');
            $table->unique(['restaurant_id', 'plan_code'], 'oip_restaurant_plan_code_unique');
        });

        Schema::table('operational_infringement_reports', function (Blueprint $table) {
            $table->foreignId('inspection_plan_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('operational_inspection_plans')
                ->nullOnDelete();
            $table->string('reinspection_proof_url')->nullable()->after('reinspection_notes');
            $table->index(['restaurant_id', 'inspection_plan_id'], 'oir_restaurant_plan_idx');
        });
    }

    public function down(): void
    {
        Schema::table('operational_infringement_reports', function (Blueprint $table) {
            $table->dropForeign(['inspection_plan_id']);
            $table->dropIndex('oir_restaurant_plan_idx');
            $table->dropColumn(['inspection_plan_id', 'reinspection_proof_url']);
        });

        Schema::dropIfExists('operational_inspection_plans');
    }
};
