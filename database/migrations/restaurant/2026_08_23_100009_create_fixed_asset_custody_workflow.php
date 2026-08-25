<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fixed_assets')) {
            Schema::table('fixed_assets', function (Blueprint $table): void {
                if (! Schema::hasColumn('fixed_assets', 'custody_status')) {
                    $table->string('custody_status', 30)->default('unassigned')->after('status');
                }
                if (! Schema::hasColumn('fixed_assets', 'condition_status')) {
                    $table->string('condition_status', 30)->default('unassessed')->after('custody_status');
                }
                if (! Schema::hasColumn('fixed_assets', 'custodian_user_id')) {
                    $table->foreignId('custodian_user_id')->nullable()->after('condition_status')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('fixed_assets', 'custody_location')) {
                    $table->string('custody_location', 255)->nullable()->after('custodian_user_id');
                }
                if (! Schema::hasColumn('fixed_assets', 'last_inspected_at')) {
                    $table->dateTime('last_inspected_at')->nullable()->after('custody_location');
                }
            });
        }

        if (! Schema::hasTable('fixed_asset_handovers')) {
            Schema::create('fixed_asset_handovers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('restaurant_branches')->cascadeOnDelete();
                $table->string('handover_code', 80);
                $table->foreignId('handed_over_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('previous_branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
                $table->foreignId('previous_custodian_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('previous_custody_status', 30)->nullable();
                $table->string('previous_custody_location', 255)->nullable();
                $table->string('status', 30)->default('pending_acceptance'); // pending_acceptance | accepted | rejected
                $table->date('handover_date');
                $table->string('condition_at_handover', 30)->default('good');
                $table->string('custody_location', 255)->nullable();
                $table->string('evidence_path', 500)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('accepted_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();

                $table->unique(['restaurant_id', 'handover_code'], 'fixed_asset_handovers_code_unique');
                $table->index(['restaurant_id', 'branch_id', 'status'], 'fixed_asset_handovers_scope_index');
                $table->index(['fixed_asset_id', 'status'], 'fixed_asset_handovers_asset_status_index');
                $table->index(['to_user_id', 'status'], 'fixed_asset_handovers_recipient_status_index');
            });
        }

        if (! Schema::hasTable('fixed_asset_inspections')) {
            Schema::create('fixed_asset_inspections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
                $table->foreignId('fixed_asset_handover_id')->nullable()->constrained('fixed_asset_handovers')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
                $table->string('inspection_code', 80);
                $table->foreignId('inspector_id')->constrained('users')->cascadeOnDelete();
                $table->string('inspection_type', 30)->default('routine'); // handover | routine | surprise | incident
                $table->date('inspected_at');
                $table->string('condition_status', 30);
                $table->string('result', 30); // pass | needs_action | fail
                $table->unsignedTinyInteger('score')->nullable();
                $table->text('findings');
                $table->text('action_required')->nullable();
                $table->string('evidence_path', 500)->nullable();
                $table->string('status', 30)->default('completed');
                $table->timestamps();

                $table->unique(['restaurant_id', 'inspection_code'], 'fixed_asset_inspections_code_unique');
                $table->index(['restaurant_id', 'branch_id', 'result'], 'fixed_asset_inspections_scope_result_index');
                $table->index(['fixed_asset_id', 'inspected_at'], 'fixed_asset_inspections_asset_date_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_inspections');
        Schema::dropIfExists('fixed_asset_handovers');

        if (Schema::hasTable('fixed_assets')) {
            Schema::table('fixed_assets', function (Blueprint $table): void {
                if (Schema::hasColumn('fixed_assets', 'custodian_user_id')) {
                    $table->dropForeign(['custodian_user_id']);
                }
                $columns = ['custody_status', 'condition_status', 'custodian_user_id', 'custody_location', 'last_inspected_at'];
                $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('fixed_assets', $column)));
                if ($existing !== []) {
                    $table->dropColumn($existing);
                }
            });
        }
    }
};
