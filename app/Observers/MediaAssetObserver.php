<?php

namespace App\Observers;

use App\Models\MediaAsset;
use App\Models\Restaurant;
use App\Notifications\StorageQuotaWarningNotification;
use App\Services\QuotaService;

class MediaAssetObserver
{
    public function __construct(protected QuotaService $quotaService) {}

    public function created(MediaAsset $mediaAsset): void
    {
        $restaurant = $mediaAsset->restaurant;
        if (! $restaurant) {
            return;
        }

        $summary = $this->quotaService->getSummary($restaurant);
        $storageInfo = $summary['resources']['storage_mb'] ?? null;

        if ($storageInfo && ! $storageInfo['unlimited']) {
            $percentage = $storageInfo['percentage'];
            if ($percentage >= 90) {
                $lastSent = $restaurant->storage_warning_sent_at;
                if (! $lastSent || now()->diffInHours($lastSent) >= 24) {
                    $owner = $restaurant->owner;
                    if ($owner) {
                        $owner->notify(new StorageQuotaWarningNotification(
                            $restaurant->name,
                            $storageInfo['used'],
                            $storageInfo['limit'],
                            $percentage
                        ));
                        $restaurant->update(['storage_warning_sent_at' => now()]);
                    }
                }
            }
        }
    }

    public function deleted(MediaAsset $mediaAsset): void
    {
        $this->resetWarningIfBelowThreshold($mediaAsset->restaurant);
    }

    public function forceDeleted(MediaAsset $mediaAsset): void
    {
        $this->resetWarningIfBelowThreshold($mediaAsset->restaurant);
    }

    protected function resetWarningIfBelowThreshold(?Restaurant $restaurant): void
    {
        if (! $restaurant || ! $restaurant->storage_warning_sent_at) {
            return;
        }

        $summary = $this->quotaService->getSummary($restaurant);
        $storageInfo = $summary['resources']['storage_mb'] ?? null;

        if ($storageInfo && ! $storageInfo['unlimited']) {
            if ($storageInfo['percentage'] < 90) {
                $restaurant->update(['storage_warning_sent_at' => null]);
            }
        }
    }
}
