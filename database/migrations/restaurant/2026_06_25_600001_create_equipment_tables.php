<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->enum('category', ['kitchen', 'refrigeration', 'cleaning', 'pos', 'hvac', 'furniture', 'other'])->default('other');
            $table->string('brand', 100)->nullable();
            $table->string('model_number', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->default(0);
            $table->unsignedInteger('warranty_months')->nullable();
            $table->string('location', 255)->nullable();
            $table->enum('status', ['active', 'maintenance', 'broken', 'retired'])->default('active');
            $table->string('image_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
        });

        Schema::create('equipment_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->enum('type', ['scheduled', 'repair', 'inspection', 'replacement'])->default('repair');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->date('scheduled_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('photo_url', 500)->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'equipment_id', 'status'], 'eml_rest_equip_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_logs');
        Schema::dropIfExists('equipment');
    }
};
