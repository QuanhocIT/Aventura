<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('target_type'); // 'all', 'plan', 'trial'
            $table->foreignId('target_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('target_role')->default('all_staff'); // 'owner', 'all_staff'
            $table->json('channels'); // ['websocket', 'email', 'push']
            $table->string('status')->default('draft'); // 'draft', 'sending', 'sent', 'failed'
            $table->unsignedInteger('sent_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
    }
};
