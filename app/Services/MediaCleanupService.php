<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaCleanupService
{
    public function orphanQuery(?int $restaurantId = null, bool $respectGracePeriod = true)
    {
        $types = MediaAsset::query()
            ->whereNotNull('attachable_type')
            ->distinct()
            ->pluck('attachable_type');

        $query = MediaAsset::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($types) {
                $q->whereNull('attachable_id')
                    ->orWhereNull('attachable_type');

                foreach ($types as $type) {
                    $className = Relation::getMorphedModel($type) ?? $type;
                    if (! class_exists($className)) {
                        continue;
                    }

                    $table = (new $className)->getTable();
                    $q->orWhere(function ($sub) use ($type, $table) {
                        $sub->where('attachable_type', $type)
                            ->whereNotExists(function ($existsQuery) use ($table) {
                                $existsQuery->select(DB::raw(1))
                                    ->from($table)
                                    ->whereColumn("{$table}.id", 'media_assets.attachable_id');
                            });
                    });
                }
            })
            ->whereNotExists(function ($holdQuery) {
                $holdQuery->select(DB::raw(1))
                    ->from('restaurants')
                    ->whereColumn('restaurants.id', 'media_assets.restaurant_id')
                    ->where('restaurants.data_legal_hold', true);
            });

        if ($respectGracePeriod) {
            $query->where('created_at', '<', now()->subDays((int) config('data_lifecycle.storage.orphan_grace_days', 30)));
        }

        if ($restaurantId !== null) {
            $query->where('restaurant_id', $restaurantId);
        }

        return $query;
    }

    public function preview(?int $restaurantId = null): array
    {
        $query = $this->orphanQuery($restaurantId);

        return [
            'count' => (clone $query)->count(),
            'bytes' => (int) (clone $query)->sum('size_bytes'),
            'grace_days' => (int) config('data_lifecycle.storage.orphan_grace_days', 30),
            'dry_run' => true,
        ];
    }

    public function cleanup(?int $restaurantId = null, bool $dryRun = true): array
    {
        $query = $this->orphanQuery($restaurantId)
            ->orderBy('id')
            ->limit((int) config('data_lifecycle.storage.orphan_batch_size', 500));

        $found = 0;
        $deleted = 0;
        $freedBytes = 0;
        $failed = 0;

        foreach ($query->cursor() as $asset) {
            $found++;

            if ($dryRun) {
                $freedBytes += (int) $asset->size_bytes;

                continue;
            }

            try {
                $disk = $asset->disk ?: config('data_lifecycle.media.disk', 'local');
                if ($asset->file_path && Storage::disk($disk)->exists($asset->file_path)) {
                    Storage::disk($disk)->delete($asset->file_path);
                }

                $freedBytes += (int) $asset->size_bytes;
                $asset->forceDelete();
                $deleted++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Data lifecycle could not delete orphan media asset.', [
                    'media_asset_id' => $asset->id,
                    'path' => $asset->file_path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'found' => $found,
            'deleted' => $deleted,
            'failed' => $failed,
            'freed_bytes' => $freedBytes,
            'freed_mb' => round($freedBytes / 1024 / 1024, 2),
            'dry_run' => $dryRun,
        ];
    }
}
