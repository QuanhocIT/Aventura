<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_feedback', function (Blueprint $table): void {
            $table->string('feedback_token', 64)
                ->nullable()
                ->after('resolution_notes')
                ->unique('customer_feedback_token_unique');
        });

        DB::table('customer_feedback')
            ->whereNull('feedback_token')
            ->orderBy('id')
            ->chunkById(100, function ($feedbacks): void {
                foreach ($feedbacks as $feedback) {
                    DB::table('customer_feedback')
                        ->where('id', $feedback->id)
                        ->update(['feedback_token' => Str::random(64)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('customer_feedback', function (Blueprint $table): void {
            $table->dropUnique('customer_feedback_token_unique');
            $table->dropColumn('feedback_token');
        });
    }
};
