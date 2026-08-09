<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_infringement_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
            $table->string('report_code')->unique();
            $table->foreignId('inspector_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('policy_id')->nullable()->constrained('company_policies')->nullOnDelete();
            $table->foreignId('offender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('infringement_date');
            $table->text('description');
            $table->string('proof_photo_url')->nullable();
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->string('status')->default('pending_owner_approval'); // pending_owner_approval, approved, rejected
            $table->text('owner_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index('branch_id');
            $table->index('offender_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_infringement_reports');
    }
};
