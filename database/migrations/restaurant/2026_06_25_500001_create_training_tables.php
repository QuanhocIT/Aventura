<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Khóa đào tạo
        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['onboarding', 'menu', 'attp', 'operations', 'custom'])->default('custom');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['restaurant_id', 'is_active']);
        });

        // Bài học / Tài liệu trong khóa
        Schema::create('training_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->string('title');
            $table->enum('content_type', ['text', 'video', 'pdf', 'link'])->default('text');
            $table->text('content')->nullable();
            $table->string('file_url', 500)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Bài kiểm tra (Quiz)
        Schema::create('training_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('pass_score')->default(70);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->json('questions');
            $table->timestamps();
        });

        // Đăng ký / Enrollment nhân viên
        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('status', ['enrolled', 'in_progress', 'completed', 'failed'])->default('enrolled');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->json('completed_lessons')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('certificate_code', 20)->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'employee_id'], 'te_course_employee');
            $table->index(['restaurant_id', 'status'], 'te_restaurant_status');
        });

        // Lần làm quiz
        Schema::create('training_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('training_enrollments')->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained('training_quizzes')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->boolean('passed')->default(false);
            $table->json('answers')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_quiz_attempts');
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_quizzes');
        Schema::dropIfExists('training_lessons');
        Schema::dropIfExists('training_courses');
    }
};
