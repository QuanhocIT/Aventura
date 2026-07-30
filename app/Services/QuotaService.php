<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\Restaurant;

class QuotaService
{
    // null = không giới hạn
    // null = không giới hạn
    public function getLimit(Restaurant $restaurant, string $resource): ?int
    {
        if ($resource === 'storage_mb' && $restaurant->custom_storage_limit_mb !== null) {
            return $restaurant->custom_storage_limit_mb;
        }

        // 1. Ưu tiên đọc từ Locked-in Subscription Snapshot (Bảo vệ khách hàng cũ khỏi xung đột khi sửa Gói Master)
        $subscription = $restaurant->subscriptions()->where('status', 'active')->latest()->first();
        $snapshot = $subscription?->meta['snapshot'] ?? null;

        if ($snapshot) {
            return match ($resource) {
                'branches' => $snapshot['max_branches'] ?? null,
                'tables' => $snapshot['max_tables'] ?? null,
                'employees' => $snapshot['max_users'] ?? null,
                'dishes' => $snapshot['max_dishes'] ?? null,
                'areas' => isset($snapshot['features']['max_areas']) ? ($snapshot['features']['max_areas'] === null ? null : (int) $snapshot['features']['max_areas']) : 2,
                'storage_mb' => (int) ($snapshot['features']['max_storage_mb'] ?? 500),
                default => null,
            };
        }

        $plan = $restaurant->plan;

        if (! $plan) {
            return 0;
        }

        return match ($resource) {
            'branches' => $plan->max_branches,
            'tables' => $plan->max_tables,
            'employees' => $plan->max_users,
            'dishes' => $plan->max_dishes,
            'areas' => isset($plan->features['max_areas']) ? ($plan->features['max_areas'] === null ? null : (int) $plan->features['max_areas']) : 2,
            'storage_mb' => (int) ($plan->features['max_storage_mb'] ?? 500),
            default => null,
        };
    }

    public function isUnlimited(Restaurant $restaurant, string $resource): bool
    {
        return $this->getLimit($restaurant, $resource) === null;
    }

    public function getResourceUsage(Restaurant $restaurant, string $resource): float
    {
        return match ($resource) {
            'branches' => (float) $restaurant->branches()->count(),
            'tables' => (float) $restaurant->tables()->count(),
            'employees' => (float) $restaurant->employees()->where('status', 'active')->count(),
            'areas' => (float) $restaurant->areas()->count(),
            'storage_mb' => (float) round(MediaAsset::where('restaurant_id', $restaurant->id)->sum('size_bytes') / (1024 * 1024), 2),
            'dishes' => (float) Product::where('restaurant_id', $restaurant->id)->count(),
            default => 0.0,
        };
    }

    public function getUsage(Restaurant $restaurant): array
    {
        return [
            'branches' => (int) $this->getResourceUsage($restaurant, 'branches'),
            'tables' => (int) $this->getResourceUsage($restaurant, 'tables'),
            'employees' => (int) $this->getResourceUsage($restaurant, 'employees'),
            'areas' => (int) $this->getResourceUsage($restaurant, 'areas'),
            'storage_mb' => $this->getResourceUsage($restaurant, 'storage_mb'),
            'dishes' => (int) $this->getResourceUsage($restaurant, 'dishes'),
        ];
    }

    public function canAdd(Restaurant $restaurant, string $resource, ?array $usage = null): bool
    {
        if ($this->isUnlimited($restaurant, $resource)) {
            return true;
        }

        $limit = $this->getLimit($restaurant, $resource);

        if ($usage !== null) {
            $used = $usage[$resource] ?? 0;
        } else {
            $used = $this->getResourceUsage($restaurant, $resource);
        }

        return $used < ($limit ?? 0);
    }

    public function hasFeature(Restaurant $restaurant, string $feature): bool
    {
        // 1. Ưu tiên đọc từ Locked-in Subscription Snapshot (Bảo vệ khách hàng cũ)
        $subscription = $restaurant->subscriptions()->where('status', 'active')->latest()->first();
        $snapshot = $subscription?->meta['snapshot'] ?? null;

        if ($snapshot && isset($snapshot['features'])) {
            return (bool) ($snapshot['features'][$feature] ?? false);
        }

        $plan = $restaurant->plan;

        if (! $plan) {
            return false;
        }

        return (bool) ($plan->features[$feature] ?? false);
    }

    public function getRateLimit(Restaurant $restaurant): int
    {
        $plan = $restaurant->plan;

        if (! $plan) {
            return 30;
        }

        return (int) ($plan->features['api_rate_limit'] ?? 60);
    }

    public function getSummary(Restaurant $restaurant): array
    {
        $usage = $this->getUsage($restaurant);

        $format = function (string $res) use ($restaurant, $usage) {
            $limit = $this->getLimit($restaurant, $res);
            $unlimited = $limit === null;
            $used = $usage[$res] ?? 0;

            return [
                'used' => $used,
                'limit' => $limit,
                'unlimited' => $unlimited,
                'percentage' => $unlimited ? 0 : $this->percentage($used, $limit ?? 0),
                'can_add' => $this->canAdd($restaurant, $res, $usage),
            ];
        };

        return [
            'plan' => $restaurant->plan?->name ?? 'Unknown',
            'plan_code' => $restaurant->plan?->code ?? 'free',
            'resources' => [
                'branches' => $format('branches'),
                'tables' => $format('tables'),
                'employees' => $format('employees'),
                'areas' => $format('areas'),
                'storage_mb' => $format('storage_mb'),
                'dishes' => $format('dishes'),
            ],
            'features' => [
                'kitchen_display' => $this->hasFeature($restaurant, 'kitchen_display'),
                'qr_ordering' => $this->hasFeature($restaurant, 'qr_ordering'),
                'inventory_basic' => $this->hasFeature($restaurant, 'inventory_basic'),
                'hr_timekeeping' => $this->hasFeature($restaurant, 'hr_timekeeping'),
                'hr_full' => $this->hasFeature($restaurant, 'hr_full'),
                'advanced_analytics' => $this->hasFeature($restaurant, 'advanced_analytics'),
                'realtime' => $this->hasFeature($restaurant, 'realtime'),
                'fraud_detection' => $this->hasFeature($restaurant, 'fraud_detection'),
                'email_reports' => $this->hasFeature($restaurant, 'email_reports'),
                'ai_advisor' => $this->hasFeature($restaurant, 'ai_advisor'),
                'supplier_portal' => $this->hasFeature($restaurant, 'supplier_portal'),
                'ai_forecasting' => $this->hasFeature($restaurant, 'ai_forecasting'),
                'api_access' => $this->hasFeature($restaurant, 'api_access'),
            ],
            'rate_limit' => $this->getRateLimit($restaurant),
        ];
    }

    private function percentage(float $used, int $limit): int
    {
        if ($limit <= 0) {
            return 0;
        }

        return (int) min(100, round($used / $limit * 100));
    }
}
