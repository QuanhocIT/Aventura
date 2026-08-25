<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_manifests', function (Blueprint $table): void {
            if (! Schema::hasColumn('delivery_manifests', 'completed_by')) {
                $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('delivery_manifests', 'receipt_notes')) {
                $table->text('receipt_notes')->nullable()->after('completed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_manifests', function (Blueprint $table): void {
            if (Schema::hasColumn('delivery_manifests', 'completed_by')) {
                $table->dropForeign(['completed_by']);
                $table->dropColumn('completed_by');
            }
            if (Schema::hasColumn('delivery_manifests', 'receipt_notes')) {
                $table->dropColumn('receipt_notes');
            }
        });
    }
};
