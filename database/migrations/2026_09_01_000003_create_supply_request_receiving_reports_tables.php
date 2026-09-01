<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supply_request_receiving_reports')) {
            Schema::create('supply_request_receiving_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('supply_request_id');
            $table->string('report_code', 60)->unique();
            $table->string('status', 40)->default('pending_branch_confirmation');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('submitted_by');
            $table->dateTime('submitted_at');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->unsignedBigInteger('driver_confirmed_by')->nullable();
            $table->dateTime('driver_confirmed_at')->nullable();
            $table->text('driver_confirmation_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('receipt_photo_path', 500)->nullable();
            $table->string('receipt_photo_hash', 64)->nullable();
            $table->string('receiver_signature_path', 500)->nullable();
            $table->string('receiver_signature_hash', 64)->nullable();
            $table->decimal('temperature_min_c', 6, 2)->nullable();
            $table->decimal('temperature_max_c', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('submitted_payload_hash', 64);
            $table->string('confirmed_payload_hash', 64)->nullable();
            $table->timestamps();

            $table->unique('supply_request_id', 'supply_request_receiving_reports_request_unique');
            $table->index(['restaurant_id', 'status'], 'supply_request_receiving_reports_queue_index');
            $table->foreign('restaurant_id', 'srr_reports_restaurant_fk')->references('id')->on('restaurants')->cascadeOnDelete();
            $table->foreign('supply_request_id', 'srr_reports_request_fk')->references('id')->on('central_supply_requests')->cascadeOnDelete();
            $table->foreign('submitted_by', 'srr_reports_submitted_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('confirmed_by', 'srr_reports_confirmed_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('driver_confirmed_by', 'srr_reports_driver_confirmed_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by', 'srr_reports_reviewed_by_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('supply_request_receiving_report_items')) {
            Schema::create('supply_request_receiving_report_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('receiving_report_id');
            $table->unsignedBigInteger('supply_request_item_id');
            $table->unsignedBigInteger('ingredient_id');
            $table->string('ingredient_name_snapshot', 255);
            $table->string('unit_symbol_snapshot', 40)->nullable();
            $table->decimal('dispatched_quantity', 12, 3)->default(0);
            $table->decimal('submitted_received_quantity', 12, 3)->default(0);
            $table->decimal('submitted_good_quantity', 12, 3)->default(0);
            $table->decimal('submitted_damaged_quantity', 12, 3)->default(0);
            $table->decimal('submitted_expired_quantity', 12, 3)->default(0);
            $table->decimal('submitted_wrong_item_quantity', 12, 3)->default(0);
            $table->decimal('submitted_shortage_quantity', 12, 3)->default(0);
            $table->string('submitted_condition', 30)->nullable();
            $table->text('submitted_note')->nullable();
            $table->decimal('confirmed_received_quantity', 12, 3)->nullable();
            $table->decimal('confirmed_good_quantity', 12, 3)->nullable();
            $table->decimal('confirmed_damaged_quantity', 12, 3)->nullable();
            $table->decimal('confirmed_expired_quantity', 12, 3)->nullable();
            $table->decimal('confirmed_wrong_item_quantity', 12, 3)->nullable();
            $table->decimal('confirmed_shortage_quantity', 12, 3)->nullable();
            $table->string('confirmed_condition', 30)->nullable();
            $table->text('confirmed_note')->nullable();
            $table->string('resolution', 30)->nullable();
            $table->unsignedBigInteger('inventory_transaction_id')->nullable();
            $table->unsignedBigInteger('quarantine_id')->nullable();
            $table->timestamps();

            $table->unique(['receiving_report_id', 'supply_request_item_id'], 'supply_request_receiving_report_item_unique');
            $table->index(['receiving_report_id', 'resolution'], 'supply_request_receiving_report_resolution_index');
            $table->foreign('receiving_report_id', 'srr_report_items_report_fk')->references('id')->on('supply_request_receiving_reports')->cascadeOnDelete();
            $table->foreign('supply_request_item_id', 'srr_report_items_request_item_fk')->references('id')->on('central_supply_request_items')->cascadeOnDelete();
            $table->foreign('ingredient_id', 'srr_report_items_ingredient_fk')->references('id')->on('ingredients')->restrictOnDelete();
            $table->foreign('inventory_transaction_id', 'srr_report_items_transaction_fk')->references('id')->on('inventory_transactions')->nullOnDelete();
            $table->foreign('quarantine_id', 'srr_report_items_quarantine_fk')->references('id')->on('inventory_quarantines')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_request_receiving_report_items');
        Schema::dropIfExists('supply_request_receiving_reports');
    }
};
