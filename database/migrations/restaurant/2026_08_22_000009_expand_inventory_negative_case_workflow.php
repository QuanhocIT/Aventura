<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_negative_cases')) {
            return;
        }

        Schema::table('inventory_negative_cases', function (Blueprint $table): void {
            // pending_owner_approval is longer than the original 20-char
            // column. MySQL strict mode would otherwise reject a valid case.
            $table->string('status', 32)->change();
            $table->string('case_code', 32)->nullable()->after('id');
            $table->decimal('detected_quantity', 12, 3)->nullable()->after('negative_quantity');
            $table->decimal('detected_value', 15, 2)->nullable()->after('estimated_value');
            $table->unsignedInteger('sla_hours')->default(48)->after('severity');
            $table->timestamp('due_at')->nullable()->after('detected_at');
            $table->timestamp('acknowledged_at')->nullable()->after('due_at');
            $table->timestamp('last_activity_at')->nullable()->after('acknowledged_at');
            $table->string('root_cause_code', 50)->nullable()->after('root_cause');
            $table->text('containment_action')->nullable()->after('root_cause_code');
            $table->text('corrective_action')->nullable()->after('containment_action');
            $table->string('verification_status', 20)->default('not_ready')->after('corrective_action');
            $table->timestamp('verification_requested_at')->nullable()->after('verification_status');
            $table->foreignId('verification_requested_by')->nullable()->after('verification_requested_at')->constrained('users')->nullOnDelete();
            $table->text('verification_note')->nullable()->after('verification_requested_by');
            $table->unsignedBigInteger('verification_transaction_id')->nullable()->after('verification_note');
            $table->foreignId('verified_by')->nullable()->after('verification_transaction_id')->constrained('users')->nullOnDelete();
            $table->decimal('verified_quantity', 12, 3)->nullable()->after('verified_by');
            $table->timestamp('verified_at')->nullable()->after('verified_quantity');
            $table->unsignedInteger('reopen_count')->default(0)->after('verified_at');
            $table->timestamp('reopened_at')->nullable()->after('reopen_count');
            $table->text('reopened_reason')->nullable()->after('reopened_at');

            $table->unique('case_code', 'inventory_negative_cases_case_code_unique');
            $table->index(['restaurant_id', 'verification_status', 'status'], 'inventory_negative_cases_verification_status_index');
            $table->index(['restaurant_id', 'due_at', 'status'], 'inventory_negative_cases_sla_index');
            $table->index('verification_transaction_id', 'inventory_negative_cases_verification_transaction_index');
        });

        DB::table('inventory_negative_cases')
            ->whereNull('detected_quantity')
            ->update([
                'detected_quantity' => DB::raw('negative_quantity'),
                'detected_value' => DB::raw('estimated_value'),
            ]);

        DB::table('inventory_negative_cases')
            ->whereNull('case_code')
            ->orderBy('id')
            ->get(['id', 'detected_at'])
            ->each(function (object $case): void {
                $date = $case->detected_at ? date('Ymd', strtotime((string) $case->detected_at)) : date('Ymd');
                DB::table('inventory_negative_cases')
                    ->where('id', $case->id)
                    ->update(['case_code' => 'NEG-'.$date.'-'.str_pad((string) $case->id, 6, '0', STR_PAD_LEFT)]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_negative_cases')) {
            return;
        }

        Schema::table('inventory_negative_cases', function (Blueprint $table): void {
            $table->dropForeign(['verification_requested_by']);
            $table->dropForeign(['verified_by']);
            $table->dropUnique('inventory_negative_cases_case_code_unique');
            $table->dropIndex('inventory_negative_cases_verification_status_index');
            $table->dropIndex('inventory_negative_cases_sla_index');
            $table->dropIndex('inventory_negative_cases_verification_transaction_index');
            $table->dropColumn([
                'case_code',
                'detected_quantity',
                'detected_value',
                'sla_hours',
                'due_at',
                'acknowledged_at',
                'last_activity_at',
                'root_cause_code',
                'containment_action',
                'corrective_action',
                'verification_status',
                'verification_requested_at',
                'verification_requested_by',
                'verification_note',
                'verification_transaction_id',
                'verified_by',
                'verified_quantity',
                'verified_at',
                'reopen_count',
                'reopened_at',
                'reopened_reason',
            ]);
        });

        // Deliberately keep status at 32 characters on rollback: existing
        // pending_owner_approval rows cannot safely be narrowed to 20.
    }
};
