<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class SyncSearchIndex implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $action,
        protected string $modelClass,
        protected string $indexName
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = 'meilisearch_sync_' . $this->indexName;
        
        Cache::put($cacheKey, [
            'status' => 'processing',
            'action' => $this->action,
            'completed_at' => null,
            'failed_at' => null,
            'error' => null,
        ]);

        try {
            if ($this->action === 'import') {
                Artisan::call('scout:import', ['model' => $this->modelClass]);
            } elseif ($this->action === 'flush') {
                Artisan::call('scout:flush', ['model' => $this->modelClass]);
            }

            Cache::put($cacheKey, [
                'status' => 'success',
                'action' => $this->action,
                'completed_at' => now()->toDateTimeString(),
                'failed_at' => null,
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'action' => $this->action,
                'completed_at' => null,
                'failed_at' => now()->toDateTimeString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
