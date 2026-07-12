<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyCodes = ['max', 'ultra'];

        foreach ($legacyCodes as $oldCode) {
            $legacyPlan = DB::table('subscription_plans')->where('code', $oldCode)->first();
            if (!$legacyPlan) {
                continue;
            }

            $newCode = match ($oldCode) {
                'max' => 'pro',
                'ultra' => 'enterprise',
            };

            $newPlan = DB::table('subscription_plans')->where('code', $newCode)->first();
            if (!$newPlan) {
                continue;
            }

            DB::table('restaurants')
                ->where('plan_id', $legacyPlan->id)
                ->update(['plan_id' => $newPlan->id]);

            DB::table('restaurant_subscriptions')
                ->where('plan_id', $legacyPlan->id)
                ->update(['plan_id' => $newPlan->id]);

            DB::table('subscription_plans')
                ->where('id', $legacyPlan->id)
                ->delete();
        }
    }

    public function down(): void {}
};
