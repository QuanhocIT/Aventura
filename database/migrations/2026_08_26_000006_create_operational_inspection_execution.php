<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'oi_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('inspection_plan_id')->nullable()->constrained('operational_inspection_plans', 'id', 'oi_plan_fk')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('restaurant_branches', 'id', 'oi_branch_fk')->cascadeOnDelete();
            $table->string('inspection_code');
            $table->string('title');
            $table->string('inspection_type')->default('routine');
            $table->string('status')->default('draft');
            $table->foreignId('lead_inspector_id')->nullable()->constrained('users', 'id', 'oi_lead_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'oi_creator_fk')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users', 'id', 'oi_completer_fk')->nullOnDelete();
            $table->json('participants')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('scope')->nullable();
            $table->text('conclusion')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('risk_level')->nullable();
            $table->text('location_note')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'inspection_code'], 'oi_restaurant_code_unique');
            $table->index(['restaurant_id', 'status', 'scheduled_at'], 'oi_restaurant_status_schedule_idx');
            $table->index(['restaurant_id', 'branch_id', 'completed_at'], 'oi_restaurant_branch_completed_idx');
        });

        Schema::table('operational_infringement_reports', function (Blueprint $table): void {
            $table->foreignId('operational_inspection_id')
                ->nullable()
                ->after('inspection_plan_id')
                ->constrained('operational_inspections', 'id', 'oir_inspection_fk')
                ->nullOnDelete();
            $table->string('assignment_status')->default('unassigned')->after('status');
            $table->dateTime('assigned_at')->nullable()->after('assignment_status');
            $table->dateTime('assignment_accepted_at')->nullable()->after('assigned_at');
            $table->dateTime('assignment_rejected_at')->nullable()->after('assignment_accepted_at');
            $table->text('assignment_rejection_reason')->nullable()->after('assignment_rejected_at');
            $table->dateTime('work_started_at')->nullable()->after('assignment_rejection_reason');
            $table->string('finding_category')->nullable()->after('description');
            $table->string('requirement_reference')->nullable()->after('finding_category');
            $table->text('observed_condition')->nullable()->after('requirement_reference');
            $table->text('root_cause')->nullable()->after('observed_condition');
            $table->text('corrective_action')->nullable()->after('root_cause');
            $table->text('preventive_action')->nullable()->after('corrective_action');
            $table->foreignId('branch_acknowledged_by')->nullable()->after('preventive_action')->constrained('users', 'id', 'oir_ack_user_fk')->nullOnDelete();
            $table->dateTime('branch_acknowledged_at')->nullable()->after('branch_acknowledged_by');
            $table->text('branch_response')->nullable()->after('branch_acknowledged_at');
            $table->unsignedInteger('reopen_count')->default(0)->after('branch_response');
            $table->dateTime('last_reopened_at')->nullable()->after('reopen_count');

            $table->index(['restaurant_id', 'assignment_status'], 'oir_restaurant_assignment_status_idx');
            $table->index(['restaurant_id', 'operational_inspection_id'], 'oir_restaurant_inspection_idx');
        });

        Schema::create('operational_corrective_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'oca_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('operational_report_id')->nullable()->constrained('operational_infringement_reports', 'id', 'oca_report_fk')->cascadeOnDelete();
            $table->foreignId('operational_inspection_id')->nullable()->constrained('operational_inspections', 'id', 'oca_inspection_fk')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_action')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users', 'id', 'oca_assignee_fk')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users', 'id', 'oca_verifier_fk')->nullOnDelete();
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');
            $table->date('due_date')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('submission_notes')->nullable();
            $table->text('verification_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['restaurant_id', 'status', 'due_date'], 'oca_restaurant_status_due_idx');
            $table->index(['restaurant_id', 'assigned_to', 'status'], 'oca_restaurant_assignee_status_idx');
        });

        Schema::create('operational_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'oe_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('operational_inspection_id')->nullable()->constrained('operational_inspections', 'id', 'oe_inspection_fk')->cascadeOnDelete();
            $table->foreignId('operational_report_id')->nullable()->constrained('operational_infringement_reports', 'id', 'oe_report_fk')->cascadeOnDelete();
            $table->foreignId('corrective_action_id')->nullable()->constrained('operational_corrective_actions', 'id', 'oe_action_fk')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users', 'id', 'oe_uploader_fk')->cascadeOnDelete();
            $table->string('collection')->default('inspection');
            $table->string('disk')->default('local');
            $table->string('path', 500);
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->dateTime('captured_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'collection'], 'oe_restaurant_collection_idx');
            $table->index(['operational_report_id', 'corrective_action_id'], 'oe_report_action_idx');
        });

        Schema::create('operational_case_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants', 'id', 'ocl_restaurant_fk')->cascadeOnDelete();
            $table->foreignId('operational_report_id')->nullable()->constrained('operational_infringement_reports', 'id', 'ocl_report_fk')->cascadeOnDelete();
            $table->foreignId('operational_inspection_id')->nullable()->constrained('operational_inspections', 'id', 'ocl_inspection_fk')->cascadeOnDelete();
            $table->string('link_type');
            $table->unsignedBigInteger('link_id');
            $table->foreignId('linked_by')->constrained('users', 'id', 'ocl_linked_by_fk')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['operational_report_id', 'link_type', 'link_id'], 'ocl_report_type_id_unique');
            $table->index(['restaurant_id', 'link_type', 'link_id'], 'ocl_restaurant_type_id_idx');
        });

        Schema::table('checklist_completions', function (Blueprint $table): void {
            $table->foreignId('operational_inspection_id')->nullable()->after('item_id')->constrained('operational_inspections', 'id', 'cc_inspection_fk')->nullOnDelete();
            $table->string('result')->default('pass')->after('notes');
            $table->text('finding_notes')->nullable()->after('result');
            $table->index(['restaurant_id', 'operational_inspection_id', 'result'], 'cc_restaurant_inspection_result_idx');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_completions', function (Blueprint $table): void {
            $table->dropForeign('cc_inspection_fk');
            $table->dropIndex('cc_restaurant_inspection_result_idx');
            $table->dropColumn(['operational_inspection_id', 'result', 'finding_notes']);
        });

        Schema::dropIfExists('operational_case_links');
        Schema::dropIfExists('operational_evidence');
        Schema::dropIfExists('operational_corrective_actions');

        Schema::table('operational_infringement_reports', function (Blueprint $table): void {
            $table->dropForeign('oir_inspection_fk');
            $table->dropForeign('oir_ack_user_fk');
            $table->dropIndex('oir_restaurant_assignment_status_idx');
            $table->dropIndex('oir_restaurant_inspection_idx');
            $table->dropColumn([
                'operational_inspection_id',
                'assignment_status',
                'assigned_at',
                'assignment_accepted_at',
                'assignment_rejected_at',
                'assignment_rejection_reason',
                'work_started_at',
                'finding_category',
                'requirement_reference',
                'observed_condition',
                'root_cause',
                'corrective_action',
                'preventive_action',
                'branch_acknowledged_by',
                'branch_acknowledged_at',
                'branch_response',
                'reopen_count',
                'last_reopened_at',
            ]);
        });

        Schema::dropIfExists('operational_inspections');
    }
};
