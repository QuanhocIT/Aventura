<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_manifests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('from_branch_id')->constrained('restaurant_branches')->onDelete('cascade');
            $table->string('manifest_code', 50)->unique();
            $table->string('route_name', 150)->nullable();
            $table->string('driver_name', 150)->nullable();
            $table->string('driver_phone', 50)->nullable();
            $table->string('vehicle_number', 50)->nullable();
            $table->string('seal_code', 50)->nullable();
            $table->string('status', 30)->default('draft'); // draft, preparing, dispatched, completed, cancelled
            $table->timestamp('scheduled_dispatch_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_manifest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_manifest_id')->constrained('delivery_manifests')->onDelete('cascade');
            $table->foreignId('supply_request_id')->constrained('central_supply_requests')->onDelete('cascade');
            $table->integer('sequence_order')->default(1);
            $table->string('status', 30)->default('pending'); // pending, loaded, delivered, disputed
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_manifest_items');
        Schema::dropIfExists('delivery_manifests');
    }
};
