<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('central_supply_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('central_supply_requests', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('central_supply_requests', 'receipt_photo_hash')) {
                $table->string('receipt_photo_hash', 64)->nullable()->after('receipt_photo_path');
            }
            if (! Schema::hasColumn('central_supply_requests', 'receiver_signature_hash')) {
                $table->string('receiver_signature_hash', 64)->nullable()->after('receiver_signature_path');
            }
        });

        Schema::table('inventory_count_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_count_sessions', 'variance_proof_path')) {
                $table->string('variance_proof_path')->nullable();
            }
            if (! Schema::hasColumn('inventory_count_sessions', 'variance_proof_hash')) {
                $table->string('variance_proof_hash', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('central_supply_requests', function (Blueprint $table) {
            $columns = collect(['approved_at', 'receipt_photo_hash', 'receiver_signature_hash'])
                ->filter(fn ($col) => Schema::hasColumn('central_supply_requests', $col))->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('inventory_count_sessions', function (Blueprint $table) {
            $columns = collect(['variance_proof_path', 'variance_proof_hash'])
                ->filter(fn ($col) => Schema::hasColumn('inventory_count_sessions', $col))->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
