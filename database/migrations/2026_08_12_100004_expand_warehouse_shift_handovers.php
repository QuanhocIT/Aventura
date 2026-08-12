<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_shift_handovers', function (Blueprint $table) {
            $table->json('open_tasks_json')->nullable()->after('notes');
            $table->json('handover_items_json')->nullable()->after('open_tasks_json');
            $table->json('incidents_json')->nullable()->after('handover_items_json');
            $table->json('stock_snapshot_json')->nullable()->after('incidents_json');
            $table->boolean('is_system_locked')->default(false)->after('signed_at');
            $table->text('lock_reason')->nullable()->after('is_system_locked');
            $table->string('acknowledgment_hash', 64)->nullable()->after('lock_reason');
            $table->string('shift_label', 50)->nullable()->after('shift_type');
            $table->index(['restaurant_id', 'shift_date']);
            $table->index(['handover_by', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_shift_handovers', function (Blueprint $table) {
            $table->dropColumn(['open_tasks_json', 'handover_items_json', 'incidents_json', 'stock_snapshot_json', 'is_system_locked', 'lock_reason', 'acknowledgment_hash', 'shift_label']);
        });
    }
};
