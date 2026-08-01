<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->timestamp('sla_due_at')->nullable()->after('resolved_at');
            $table->timestamp('escalated_at')->nullable()->after('sla_due_at');

            $table->index(['status', 'sla_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'sla_due_at']);
            $table->dropColumn(['sla_due_at', 'escalated_at']);
        });
    }
};
