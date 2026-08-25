<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_orders', function (Blueprint $table): void {
            $table->boolean('awaiting_customer_confirmation')
                ->default(false)
                ->after('status');
            $table->string('revision_note', 500)->nullable()->after('awaiting_customer_confirmation');
            $table->foreignId('revised_by')->nullable()->after('revision_note')->constrained('users')->nullOnDelete();
            $table->timestamp('revision_confirmed_at')->nullable()->after('revised_by');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_orders', function (Blueprint $table): void {
            $table->dropForeign(['revised_by']);
            $table->dropColumn([
                'awaiting_customer_confirmation',
                'revision_note',
                'revised_by',
                'revision_confirmed_at',
            ]);
        });
    }
};
