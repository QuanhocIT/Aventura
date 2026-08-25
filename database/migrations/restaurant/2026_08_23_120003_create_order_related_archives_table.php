<?php

use App\Support\Partitioning\PartitionHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_related_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('order_id');
            $table->string('source_table', 100);
            $table->string('source_id', 100)->nullable();
            $table->json('payload');
            $table->timestamp('created_at');
            $table->timestamp('archived_at')->useCurrent();

            $table->index(['restaurant_id', 'created_at'], 'order_related_restaurant_created_index');
            $table->index(['order_id', 'source_table'], 'order_related_order_table_index');
            // MySQL requires every unique key on a partitioned table to include
            // the partitioning column (created_at).
            $table->unique(['source_table', 'source_id', 'created_at'], 'order_related_source_unique');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE order_related_archives DROP PRIMARY KEY, ADD PRIMARY KEY (id, created_at)');
            $helper = new PartitionHelper;
            $config = config('partitioning');
            $columnConfig = $config['tables']['order_related_archives'];
            $partitionSql = $helper->buildInitialPartitionSql(
                $columnConfig['column'],
                $columnConfig['type'],
                $config['months_back'],
                $config['months_forward'],
            );
            DB::statement("ALTER TABLE order_related_archives {$partitionSql}");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_related_archives');
    }
};
