<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouse_receiving_reports')) {
            Schema::create('warehouse_receiving_reports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('restaurant_id');
                $table->unsignedBigInteger('voucher_id');
                $table->string('report_code', 60)->unique();
                $table->string('status', 40)->default('employee_confirmed');
                $table->string('issue_type', 40);
                $table->text('issue_summary');
                $table->unsignedBigInteger('submitted_by');
                $table->dateTime('submitted_at');
                $table->unsignedBigInteger('employee_confirmed_by');
                $table->dateTime('employee_confirmed_at');
                $table->string('quality_status', 20)->nullable();
                $table->text('quality_notes')->nullable();
                $table->text('notes')->nullable();
                $table->decimal('total_expected_qty', 12, 3)->default(0);
                $table->decimal('total_actual_qty', 12, 3)->default(0);
                $table->decimal('total_discrepancy_qty', 12, 3)->default(0);
                $table->decimal('total_value', 14, 2)->default(0);
                $table->json('evidence_paths')->nullable();
                $table->string('payload_hash', 64);
                $table->timestamps();

                $table->unique('voucher_id', 'warehouse_receiving_reports_voucher_unique');
                $table->index(['restaurant_id', 'status'], 'warehouse_receiving_reports_queue_index');
                $table->foreign('restaurant_id', 'wrr_reports_restaurant_fk')->references('id')->on('restaurants')->cascadeOnDelete();
                $table->foreign('voucher_id', 'wrr_reports_voucher_fk')->references('id')->on('warehouse_receiving_vouchers')->cascadeOnDelete();
                $table->foreign('submitted_by', 'wrr_reports_submitted_by_fk')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('employee_confirmed_by', 'wrr_reports_confirmed_by_fk')->references('id')->on('users')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('warehouse_receiving_report_items')) {
            Schema::create('warehouse_receiving_report_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('report_id');
                $table->unsignedBigInteger('voucher_item_id');
                $table->unsignedBigInteger('ingredient_id');
                $table->string('ingredient_code_snapshot', 100)->nullable();
                $table->string('ingredient_name_snapshot', 255);
                $table->string('unit_symbol_snapshot', 50)->nullable();
                $table->decimal('expected_quantity', 12, 3)->default(0);
                $table->decimal('actual_quantity', 12, 3)->default(0);
                $table->decimal('difference_quantity', 12, 3)->default(0);
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->decimal('line_value', 14, 2)->default(0);
                $table->string('lot_number', 100)->nullable();
                $table->string('expiry_date', 20)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['report_id', 'voucher_item_id'], 'warehouse_receiving_report_item_unique');
                $table->index(['report_id', 'difference_quantity'], 'warehouse_receiving_report_item_difference_index');
                $table->foreign('report_id', 'wrr_report_items_report_fk')->references('id')->on('warehouse_receiving_reports')->cascadeOnDelete();
                $table->foreign('voucher_item_id', 'wrr_report_items_voucher_item_fk')->references('id')->on('warehouse_receiving_voucher_items')->cascadeOnDelete();
                $table->foreign('ingredient_id', 'wrr_report_items_ingredient_fk')->references('id')->on('ingredients')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_receiving_report_items');
        Schema::dropIfExists('warehouse_receiving_reports');
    }
};
