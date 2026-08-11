<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gắn phiếu đếm mù, giải trình chênh lệch và bàn giao tiền vào phiếu chốt ca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shift_closings')) {
            return;
        }

        // status đang là ENUM nên không thêm được trạng thái mới về sau.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE shift_closings MODIFY status VARCHAR(20) NOT NULL DEFAULT 'draft'");
        } else {
            Schema::table('shift_closings', function (Blueprint $table): void {
                $table->string('status', 20)->default('draft')->change();
            });
        }

        Schema::table('shift_closings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shift_closings', 'cash_count_id')) {
                $table->foreignId('cash_count_id')->nullable()->after('actual_cash')
                    ->constrained('cash_counts')->nullOnDelete();
            }
            if (! Schema::hasColumn('shift_closings', 'variance_explanation')) {
                $table->text('variance_explanation')->nullable()->after('responsibility_note');
            }
            if (! Schema::hasColumn('shift_closings', 'variance_explained_at')) {
                $table->timestamp('variance_explained_at')->nullable()->after('variance_explanation');
            }
            if (! Schema::hasColumn('shift_closings', 'variance_confirmed_by')) {
                $table->foreignId('variance_confirmed_by')->nullable()->after('variance_explained_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('shift_closings', 'variance_confirmed_at')) {
                $table->timestamp('variance_confirmed_at')->nullable()->after('variance_confirmed_by');
            }
            if (! Schema::hasColumn('shift_closings', 'evidence_photo_path')) {
                $table->string('evidence_photo_path', 500)->nullable()->after('variance_confirmed_at');
            }
            if (! Schema::hasColumn('shift_closings', 'handover_id')) {
                $table->foreignId('handover_id')->nullable()->after('evidence_photo_path')
                    ->constrained('cash_handovers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shift_closings')) {
            return;
        }

        Schema::table('shift_closings', function (Blueprint $table): void {
            foreach (['cash_count_id', 'variance_confirmed_by', 'handover_id'] as $column) {
                if (Schema::hasColumn('shift_closings', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            $table->dropColumn(array_values(array_filter([
                Schema::hasColumn('shift_closings', 'variance_explanation') ? 'variance_explanation' : null,
                Schema::hasColumn('shift_closings', 'variance_explained_at') ? 'variance_explained_at' : null,
                Schema::hasColumn('shift_closings', 'variance_confirmed_at') ? 'variance_confirmed_at' : null,
                Schema::hasColumn('shift_closings', 'evidence_photo_path') ? 'evidence_photo_path' : null,
            ])));
        });
    }
};
