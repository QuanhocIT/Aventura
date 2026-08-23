<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_storage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->timestamp('snapshot_at');
            $table->unsignedBigInteger('media_bytes')->default(0);
            $table->unsignedBigInteger('media_files')->default(0);
            $table->unsignedBigInteger('database_rows')->default(0);
            $table->unsignedBigInteger('database_bytes')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->bigInteger('growth_bytes')->default(0);
            $table->json('table_stats')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'snapshot_date'], 'tenant_storage_daily_unique');
            $table->index(['snapshot_date', 'total_bytes'], 'tenant_storage_date_total_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_storage_snapshots');
    }
};
