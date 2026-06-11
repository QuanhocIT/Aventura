<?php

namespace App\Services;

use App\Models\Restaurant;

class QuotaService
{
    // null = không giới hạn
    public function getLimit(Restaurant $restaurant, string $resource): ?int
    {
        $plan = $restaurant->plan;

        if (! $plan) {
            return 0;
        }

        return match ($resource) {
            'branches' => $plan->max_branches,
            'tables' => $plan->max_tables,
            'employees' => $plan->max_users,
            'areas' => isset($plan->features['max_areas']) ? ($plan->features['max_areas'] === null ? null : (int) $plan->features['max_areas']) : 2,
            'storage_mb' => (int) ($plan->features['max_storage_mb'] ?? 500),
            default => null,
        };
    }

    public function isUnlimited(Restaurant $restaurant, string $resource): bool
    {
        return $this->getLimit($restaurant, $resource) === null;
    }

    public function getUsage(Restaurant $restaurant): array
    {
        return [
            'branches' => $restaurant->branches()->count(),
            'tables' => $restaurant->tables()->count(),
            'employees' => $restaurant->employees()->where('status', 'active')->count(),
            'areas' => $restaurant->areas()->count(),
        ];
    }

    public function canAdd(Restaurant $restaurant, string $resource): bool
    {
        if ($this->isUnlimited($restaurant, $resource)) {
            return true;
        }

        $limit = $this->getLimit($restaurant, $resource);
        $usage = $this->getUsage($restaurant);

        return ($usage[$resource] ?? 0) < ($limit ?? 0);
    }

    public function hasFeature(Restaurant $restaurant, string $feature): bool
    {
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
                'can_add' => $this->canAdd($restaurant, $res),
            ];
        };

        return [
            'plan' => $restaurant->plan?->name ?? 'Unknown',
            'plan_code' => $restaurant->plan?->code ?? 'FREE',
            'resources' => [
                'branches' => $format('branches'),
                'tables' => $format('tables'),
                'employees' => $format('employees'),
                'areas' => $format('areas'),
            ],
            'features' => [
                'ai_features' => $this->hasFeature($restaurant, 'ai_features'),
                'realtime' => $this->hasFeature($restaurant, 'realtime'),
                'advanced_analytics' => $this->hasFeature($restaurant, 'advanced_analytics'),
            ],
            'rate_limit' => $this->getRateLimit($restaurant),
        ];
    }

    private function percentage(int $used, int $limit): int
    {
        if ($limit <= 0) {
            return 0;
        }

        return (int) min(100, round($used / $limit * 100));
    }
}
