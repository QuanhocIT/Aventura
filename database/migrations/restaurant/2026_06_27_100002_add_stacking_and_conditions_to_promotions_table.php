<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->boolean('is_stackable')->default(false)->after('auto_deactivate_on_budget');
            $table->unsignedSmallInteger('stacking_priority')->default(0)->after('is_stackable');
            $table->string('stacking_group', 50)->nullable()->after('stacking_priority');
            $table->json('conditions')->nullable()->after('stacking_group');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['is_stackable', 'stacking_priority', 'stacking_group', 'conditions']);
        });
    }
};
