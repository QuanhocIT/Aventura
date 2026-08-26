<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_completions', function (Blueprint $table): void {
            // Checklist vận hành cũ vẫn được kiểm soát ở controller theo ngày;
            // phiên thanh tra cần được phép ghi cùng một mục nhiều lần/ngày.
            $table->dropUnique('cc_item_date_branch_unique');
            $table->unique(
                ['item_id', 'operational_inspection_id', 'checked_date', 'restaurant_id', 'branch_id'],
                'cc_item_inspection_date_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('checklist_completions', function (Blueprint $table): void {
            $table->dropUnique('cc_item_inspection_date_unique');
            $table->unique(['item_id', 'checked_date', 'restaurant_id', 'branch_id'], 'cc_item_date_branch_unique');
        });
    }
};
