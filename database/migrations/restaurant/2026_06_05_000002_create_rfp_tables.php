<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_for_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'closed', 'completed'])->default('open');
            $table->dateTime('due_date');
            $table->timestamps();

            $table->index(['restaurant_id', 'status'], 'rfp_restaurant_status_index');
        });

        Schema::create('rfp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfp_id')->constrained('request_for_proposals')->cascadeOnDelete();
            $table->string('ingredient_name');
            $table->decimal('quantity_required', 12, 3);
            $table->string('unit_symbol', 50)->default('kg');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rfp_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfp_id')->constrained('request_for_proposals')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->dateTime('proposed_delivery_date');
            $table->decimal('total_amount', 12, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['submitted', 'accepted', 'rejected'])->default('submitted');
            $table->timestamps();

            $table->index(['rfp_id', 'supplier_id'], 'rfp_bids_rfp_supplier_index');
        });

        Schema::create('rfp_bid_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfp_bid_id')->constrained('rfp_bids')->cascadeOnDelete();
            $table->foreignId('rfp_item_id')->constrained('rfp_items')->cascadeOnDelete();
            $table->decimal('proposed_price_per_unit', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfp_bid_items');
        Schema::dropIfExists('rfp_bids');
        Schema::dropIfExists('rfp_items');
        Schema::dropIfExists('request_for_proposals');
    }
};
