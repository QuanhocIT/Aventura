<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_courses', function (Blueprint $table): void {
            if (! Schema::hasColumn('training_courses', 'course_code')) {
                $table->string('course_code', 40)->nullable()->after('title');
            }
            if (! Schema::hasColumn('training_courses', 'version')) {
                $table->string('version', 20)->default('1.0')->after('course_code');
            }
            if (! Schema::hasColumn('training_courses', 'target_roles')) {
                $table->json('target_roles')->nullable()->after('is_required');
            }
            if (! Schema::hasColumn('training_courses', 'target_branch_ids')) {
                $table->json('target_branch_ids')->nullable()->after('target_roles');
            }
            if (! Schema::hasColumn('training_courses', 'required_for_new_hires')) {
                $table->boolean('required_for_new_hires')->default(false)->after('target_branch_ids');
            }
            if (! Schema::hasColumn('training_courses', 'due_days')) {
                $table->unsignedSmallInteger('due_days')->default(14)->after('required_for_new_hires');
            }
            if (! Schema::hasColumn('training_courses', 'validity_days')) {
                $table->unsignedInteger('validity_days')->nullable()->after('due_days');
            }
            if (! Schema::hasColumn('training_courses', 'requires_manager_signoff')) {
                $table->boolean('requires_manager_signoff')->default(false)->after('validity_days');
            }
            if (! Schema::hasColumn('training_courses', 'published_at')) {
                $table->dateTime('published_at')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('training_courses', 'archived_at')) {
                $table->dateTime('archived_at')->nullable()->after('published_at');
            }
        });

        Schema::table('training_lessons', function (Blueprint $table): void {
            if (! Schema::hasColumn('training_lessons', 'is_required')) {
                $table->boolean('is_required')->default(true)->after('duration_minutes');
            }
            if (! Schema::hasColumn('training_lessons', 'requires_acknowledgement')) {
                $table->boolean('requires_acknowledgement')->default(false)->after('is_required');
            }
        });

        Schema::table('training_quizzes', function (Blueprint $table): void {
            if (! Schema::hasColumn('training_quizzes', 'is_required')) {
                $table->boolean('is_required')->default(true)->after('max_attempts');
            }
            if (! Schema::hasColumn('training_quizzes', 'time_limit_minutes')) {
                $table->unsignedSmallInteger('time_limit_minutes')->nullable()->after('is_required');
            }
            if (! Schema::hasColumn('training_quizzes', 'randomize_questions')) {
                $table->boolean('randomize_questions')->default(false)->after('time_limit_minutes');
            }
        });

        Schema::table('training_enrollments', function (Blueprint $table): void {
            if (! Schema::hasColumn('training_enrollments', 'assigned_by')) {
                $table->foreignId('assigned_by')->nullable()->after('employee_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('training_enrollments', 'assigned_at')) {
                $table->dateTime('assigned_at')->nullable()->after('assigned_by');
            }
            if (! Schema::hasColumn('training_enrollments', 'due_at')) {
                $table->dateTime('due_at')->nullable()->after('assigned_at');
            }
            if (! Schema::hasColumn('training_enrollments', 'mandatory')) {
                $table->boolean('mandatory')->default(false)->after('due_at');
            }
            if (! Schema::hasColumn('training_enrollments', 'awaiting_manager_approval')) {
                $table->boolean('awaiting_manager_approval')->default(false)->after('mandatory');
            }
            if (! Schema::hasColumn('training_enrollments', 'assignment_reason')) {
                $table->string('assignment_reason', 120)->nullable()->after('mandatory');
            }
            if (! Schema::hasColumn('training_enrollments', 'last_activity_at')) {
                $table->dateTime('last_activity_at')->nullable()->after('started_at');
            }
            if (! Schema::hasColumn('training_enrollments', 'is_overdue')) {
                $table->boolean('is_overdue')->default(false)->after('last_activity_at');
            }
            if (! Schema::hasColumn('training_enrollments', 'manager_approved_by')) {
                $table->foreignId('manager_approved_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('training_enrollments', 'manager_approved_at')) {
                $table->dateTime('manager_approved_at')->nullable()->after('manager_approved_by');
            }
            if (! Schema::hasColumn('training_enrollments', 'last_score')) {
                $table->unsignedTinyInteger('last_score')->nullable()->after('manager_approved_at');
            }
            if (! Schema::hasColumn('training_enrollments', 'certificate_issued_at')) {
                $table->dateTime('certificate_issued_at')->nullable()->after('certificate_code');
            }
            if (! Schema::hasColumn('training_enrollments', 'certificate_expires_at')) {
                $table->dateTime('certificate_expires_at')->nullable()->after('certificate_issued_at');
            }
        });

        if (! Schema::hasTable('training_activity_logs')) {
            Schema::create('training_activity_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('enrollment_id')->constrained('training_enrollments')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('activity', 60);
                $table->json('metadata')->nullable();
                $table->dateTime('occurred_at');
                $table->timestamps();

                $table->index(['restaurant_id', 'activity', 'occurred_at']);
                $table->index(['enrollment_id', 'occurred_at']);
            });
        }

        Schema::table('training_courses', function (Blueprint $table): void {
            $table->unique(['restaurant_id', 'course_code'], 'training_course_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_activity_logs');

        Schema::table('training_enrollments', function (Blueprint $table): void {
            foreach (['assigned_by', 'manager_approved_by'] as $foreign) {
                if (Schema::hasColumn('training_enrollments', $foreign)) {
                    $table->dropForeign([$foreign]);
                }
            }
            $columns = ['assigned_by', 'assigned_at', 'due_at', 'mandatory', 'awaiting_manager_approval', 'assignment_reason', 'last_activity_at', 'is_overdue', 'manager_approved_by', 'manager_approved_at', 'last_score', 'certificate_issued_at', 'certificate_expires_at'];
            $table->dropColumn(array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('training_enrollments', $column))));
        });

        Schema::table('training_quizzes', function (Blueprint $table): void {
            $table->dropColumn(array_values(array_filter(['is_required', 'time_limit_minutes', 'randomize_questions'], fn (string $column): bool => Schema::hasColumn('training_quizzes', $column))));
        });

        Schema::table('training_lessons', function (Blueprint $table): void {
            $table->dropColumn(array_values(array_filter(['is_required', 'requires_acknowledgement'], fn (string $column): bool => Schema::hasColumn('training_lessons', $column))));
        });

        Schema::table('training_courses', function (Blueprint $table): void {
            $table->dropUnique('training_course_code_unique');
            $table->dropColumn(array_values(array_filter(['course_code', 'version', 'target_roles', 'target_branch_ids', 'required_for_new_hires', 'due_days', 'validity_days', 'requires_manager_signoff', 'published_at', 'archived_at'], fn (string $column): bool => Schema::hasColumn('training_courses', $column))));
        });
    }
};
