<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('channel')->default('admin_portal');
            $table->string('category');
            $table->string('severity');
            $table->string('priority');
            $table->string('status')->default('open');
            $table->string('title');
            $table->text('description');
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index(['severity', 'priority']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_internal')->default(false);
            $table->text('message');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('support_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('scope')->default('global');
            $table->string('audience')->default('all');
            $table->string('level')->default('info');
            $table->string('status')->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('knowledge_base_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->string('video_url')->nullable();
            $table->integer('view_count')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'category']);
        });

        Schema::create('system_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('metric_key');
            $table->string('operator')->default('>');
            $table->decimal('threshold', 10, 2);
            $table->integer('cooldown_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->json('channels')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_alert_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric_key');
            $table->string('status')->default('open');
            $table->decimal('metric_value', 10, 2)->nullable();
            $table->decimal('threshold', 10, 2)->nullable();
            $table->string('title');
            $table->text('message');
            $table->json('channels')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'triggered_at']);
            $table->index(['metric_key', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
        Schema::dropIfExists('system_alert_rules');
        Schema::dropIfExists('knowledge_base_articles');
        Schema::dropIfExists('support_announcements');
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
    }
};