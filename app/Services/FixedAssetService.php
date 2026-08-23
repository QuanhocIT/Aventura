<?php

namespace App\Services;

use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\FinancialJournalEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FixedAssetService
{
    public function depreciate(FixedAsset $asset, string $periodMonth, ?int $userId = null): FixedAssetDepreciation
    {
        $month = CarbonImmutable::createFromFormat('Y-m-d', $periodMonth.'-01')->startOfMonth();

        return DB::transaction(function () use ($asset, $month, $userId): FixedAssetDepreciation {
            $lockedAsset = FixedAsset::withoutGlobalScopes()->lockForUpdate()->findOrFail($asset->id);
            $existing = FixedAssetDepreciation::withoutGlobalScopes()
                ->where('fixed_asset_id', $lockedAsset->id)
                ->whereDate('period_month', $month->toDateString())
                ->first();
            if ($existing) {
                return $existing;
            }
            if ($lockedAsset->status !== 'active' || $lockedAsset->in_service_date->startOfMonth()->gt($month)) {
                throw new \RuntimeException('Tài sản không hoạt động trong kỳ khấu hao này.');
            }

            $remaining = max(0, (float) $lockedAsset->cost - (float) $lockedAsset->residual_value - (float) $lockedAsset->accumulated_depreciation);
            $monthly = min($remaining, round(((float) $lockedAsset->cost - (float) $lockedAsset->residual_value) / max(1, (int) $lockedAsset->useful_life_months), 2));
            if ($monthly <= 0) {
                throw new \RuntimeException('Tài sản đã khấu hao hết.');
            }

            $entry = app(FinancialPostingService::class)->post([
                'restaurant_id' => $lockedAsset->restaurant_id,
                'branch_id' => $lockedAsset->branch_id,
                'entry_date' => $month->endOfMonth(),
                'source_type' => FixedAsset::class,
                'source_id' => $lockedAsset->id,
                'idempotency_key' => 'fixed-asset:depreciation:'.$lockedAsset->id.':'.$month->format('Y-m'),
                'description' => 'Khấu hao tài sản '.$lockedAsset->asset_code.' kỳ '.$month->format('m/Y'),
                'created_by' => $userId,
                'posted_by' => $userId,
                'lines' => [
                    ['account' => '6272', 'debit' => $monthly, 'credit' => 0],
                    ['account' => '2141', 'debit' => 0, 'credit' => $monthly],
                ],
            ]);

            $depreciation = FixedAssetDepreciation::create([
                'restaurant_id' => $lockedAsset->restaurant_id,
                'fixed_asset_id' => $lockedAsset->id,
                'period_month' => $month->toDateString(),
                'amount' => $monthly,
                'journal_entry_id' => $entry->id,
                'created_by' => $userId,
            ]);
            $lockedAsset->increment('accumulated_depreciation', $monthly);

            return $depreciation;
        });
    }
}
