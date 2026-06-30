<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_feedback', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('customer_id')->constrained('employees')->nullOnDelete();
            $table->unsignedTinyInteger('employee_rating')->nullable()->after('employee_id');
            $table->json('items_feedback')->nullable()->after('employee_rating');
        });
    }

    public function down(): void
    {
        Schema::table('customer_feedback', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn(['employee_id', 'employee_rating', 'items_feedback']);
        });
    }
};
