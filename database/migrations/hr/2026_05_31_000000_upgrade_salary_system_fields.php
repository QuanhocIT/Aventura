<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('allowed_waste_ratio', 5, 2)->default(0.00)->after('average_cost');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->enum('compensation_type', ['fixed', 'hourly', 'shift'])->default('fixed')->after('base_salary');
            $table->decimal('pay_rate', 12, 2)->default(0.00)->after('compensation_type');
        });

        Schema::table('salary_adjustments', function (Blueprint $table) {
            $table->enum('status', ['applied', 'disputed', 'waived'])->default('applied')->after('amount');
            $table->string('dispute_reason', 500)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('salary_adjustments', function (Blueprint $table) {
            $table->dropColumn(['status', 'dispute_reason']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['compensation_type', 'pay_rate']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('allowed_waste_ratio');
        });
    }
};
