<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->boolean('auto_distribute')->default(false)->after('target_count');
            $table->foreignId('promotion_trigger_id')->nullable()->after('auto_distribute')->constrained('promotion_triggers')->nullOnDelete();
            $table->unsignedInteger('repeat_interval_days')->nullable()->after('promotion_trigger_id');
            $table->datetime('last_run_at')->nullable()->after('repeat_interval_days');
            $table->string('campaign_status', 20)->default('draft')->after('last_run_at');
            $table->string('email_subject', 255)->nullable()->after('campaign_status');
            $table->longText('email_html_body')->nullable()->after('email_subject');
            $table->unsignedInteger('sent_count')->default(0)->after('email_html_body');
            $table->unsignedInteger('failed_count')->default(0)->after('sent_count');
            $table->datetime('scheduled_at')->nullable()->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_trigger_id');
            $table->dropColumn([
                'auto_distribute', 'repeat_interval_days', 'last_run_at',
                'campaign_status', 'email_subject', 'email_html_body',
                'sent_count', 'failed_count', 'scheduled_at',
            ]);
        });
    }
};
