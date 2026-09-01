<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('supply_request_receiving_reports', 'transporter_id')) {
            Schema::table('supply_request_receiving_reports', function (Blueprint $table): void {
                $table->unsignedBigInteger('transporter_id')->nullable()->after('supply_request_id');
                $table->string('transporter_name_snapshot', 255)->nullable()->after('transporter_id');
                $table->index('transporter_id', 'srr_reports_transporter_idx');
                $table->foreign('transporter_id', 'srr_reports_transporter_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('supply_request_receiving_reports', 'transporter_id')) {
            return;
        }

        Schema::table('supply_request_receiving_reports', function (Blueprint $table): void {
            $table->dropForeign('srr_reports_transporter_fk');
            $table->dropIndex('srr_reports_transporter_idx');
            $table->dropColumn(['transporter_id', 'transporter_name_snapshot']);
        });
    }
};
