<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fixed_assets')) {
            Schema::create('fixed_assets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
                $table->string('asset_code', 80);
                $table->string('name', 255);
                $table->string('category', 100)->nullable();
                $table->date('purchase_date');
                $table->date('in_service_date');
                $table->decimal('cost', 15, 2);
                $table->decimal('residual_value', 15, 2)->default(0);
                $table->unsignedInteger('useful_life_months');
                $table->string('depreciation_method', 30)->default('straight_line');
                $table->decimal('accumulated_depreciation', 15, 2)->default(0);
                $table->string('status', 20)->default('active');
                $table->date('disposed_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['restaurant_id', 'asset_code']);
                $table->index(['restaurant_id', 'branch_id', 'status']);
            });
        }

        if (! Schema::hasTable('fixed_asset_depreciations')) {
            Schema::create('fixed_asset_depreciations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
                $table->date('period_month');
                $table->decimal('amount', 15, 2);
                $table->foreignId('journal_entry_id')->nullable()->constrained('financial_journal_entries')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['fixed_asset_id', 'period_month']);
                $table->index(['restaurant_id', 'period_month']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciations');
        Schema::dropIfExists('fixed_assets');
    }
};
