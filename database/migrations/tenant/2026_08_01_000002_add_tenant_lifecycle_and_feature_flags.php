<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('restaurants', 'lifecycle_status')) {
            Schema::table('restaurants', function (Blueprint $table): void {
                $table->string('lifecycle_status', 20)->default('active')->after('status');
                $table->index(['lifecycle_status', 'status'], 'restaurants_lifecycle_status_index');
            });
        }

        DB::table('restaurants')
            ->where('status', 'suspended')
            ->update(['lifecycle_status' => 'suspended']);

        if (Schema::hasColumn('restaurants', 'sandbox_mode')) {
            DB::table('restaurants')
                ->where('sandbox_mode', true)
                ->update(['lifecycle_status' => 'sandbox']);
        }

        if (! Schema::hasTable('restaurant_feature_flags')) {
            Schema::create('restaurant_feature_flags', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->string('feature', 100);
                $table->boolean('enabled')->default(false);
                $table->timestamps();

                $table->unique(['restaurant_id', 'feature'], 'restaurant_feature_flags_unique');
                $table->index(['restaurant_id', 'enabled'], 'restaurant_feature_flags_enabled_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_feature_flags');

        if (Schema::hasColumn('restaurants', 'lifecycle_status')) {
            Schema::table('restaurants', function (Blueprint $table): void {
                $table->dropIndex('restaurants_lifecycle_status_index');
                $table->dropColumn('lifecycle_status');
            });
        }
    }
};
