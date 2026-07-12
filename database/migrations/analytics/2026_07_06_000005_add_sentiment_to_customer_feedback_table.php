<?php

use App\Services\SentimentAnalysisService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_feedback', function (Blueprint $table) {
            $table->string('sentiment', 10)->nullable()->after('content');
            $table->float('sentiment_score')->nullable()->after('sentiment');
        });

        // Backfill sentiment cho feedback đã tồn tại bằng lexicon analyzer.
        $analyzer = app(SentimentAnalysisService::class);

        DB::table('customer_feedback')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($analyzer) {
                foreach ($rows as $row) {
                    $result = $analyzer->analyze($row->content);

                    DB::table('customer_feedback')
                        ->where('id', $row->id)
                        ->update([
                            'sentiment' => $result['sentiment'],
                            'sentiment_score' => $result['score'],
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('customer_feedback', function (Blueprint $table) {
            $table->dropColumn(['sentiment', 'sentiment_score']);
        });
    }
};
