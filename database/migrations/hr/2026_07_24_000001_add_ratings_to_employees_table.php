<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'rating_star')) {
                $table->decimal('rating_star', 3, 2)->default(5.00)->after('status');
            }
            if (! Schema::hasColumn('employees', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0)->after('rating_star');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'rating_star')) {
                $table->dropColumn('rating_star');
            }
            if (Schema::hasColumn('employees', 'rating_count')) {
                $table->dropColumn('rating_count');
            }
        });
    }
};
