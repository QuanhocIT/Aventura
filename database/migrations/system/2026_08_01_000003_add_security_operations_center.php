<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'security_session_version')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedInteger('security_session_version')->default(0)->after('remember_token');
            });
        }

        if (! Schema::hasColumn('users', 'force_logout_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('force_logout_at')->nullable()->after('security_session_version');
            });
        }

        if (Schema::hasTable('api_keys') && ! Schema::hasColumn('api_keys', 'rotated_at')) {
            Schema::table('api_keys', function (Blueprint $table): void {
                $table->timestamp('rotated_at')->nullable()->after('last_used_at');
            });
        }

        if (! Schema::hasTable('login_events')) {
            Schema::create('login_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('email')->nullable()->index();
                $table->string('status', 20)->index();
                $table->string('ip_address', 45)->nullable()->index();
                $table->text('user_agent')->nullable();
                $table->string('failure_reason')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->index(['user_id', 'status', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('security_alerts')) {
            Schema::create('security_alerts', function (Blueprint $table): void {
                $table->id();
                $table->string('fingerprint', 191)->unique();
                $table->string('type', 60)->index();
                $table->string('severity', 20)->index();
                $table->string('title');
                $table->text('message');
                $table->json('context')->nullable();
                $table->string('status', 20)->default('open')->index();
                $table->unsignedInteger('occurrences')->default(1);
                $table->timestamp('first_seen_at')->index();
                $table->timestamp('last_seen_at')->index();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('maintenance_modes')) {
            Schema::create('maintenance_modes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->nullable()->constrained('restaurants')->cascadeOnDelete();
                $table->boolean('enabled')->default(false)->index();
                $table->string('message', 500)->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['restaurant_id', 'enabled']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_modes');
        Schema::dropIfExists('security_alerts');
        Schema::dropIfExists('login_events');

        if (Schema::hasTable('api_keys') && Schema::hasColumn('api_keys', 'rotated_at')) {
            Schema::table('api_keys', function (Blueprint $table): void {
                $table->dropColumn('rotated_at');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'force_logout_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('force_logout_at');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'security_session_version')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('security_session_version');
            });
        }
    }
};
