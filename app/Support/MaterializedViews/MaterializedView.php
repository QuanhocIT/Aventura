<?php

namespace App\Support\MaterializedViews;

use App\Support\MaterializedViews\Contracts\MaterializedViewBuilder;
use App\Support\MaterializedViews\Contracts\RollupStore;

/**
 * Value object mô tả 1 view trong config/materialized_views.php — bất biến,
 * chỉ đọc, hydrate 1 lần từ config bởi MaterializedViewRegistry.
 */
final readonly class MaterializedView
{
    /**
     * @param  array{0: string, 1: string}|null  $between
     */
    public function __construct(
        public string $name,
        public string $builderClass,
        public string $storeClass,
        public string $summaryType,
        public string $cron,
        public ?array $between,
        public int $staleAfter,
        public string $queue,
        public int $chunk,
        public int $lockSeconds,
        public int $tries,
        public int $timeout,
        public bool $branchScoped,
        public bool $enabled,
    ) {}

    public static function fromConfig(string $name, array $view, array $defaults): self
    {
        return new self(
            name: $name,
            builderClass: $view['builder'],
            storeClass: $view['store'],
            summaryType: $view['summary_type'] ?? 'daily',
            cron: $view['cron'],
            between: $view['between'] ?? null,
            staleAfter: $view['stale_after'] ?? $defaults['stale_after'],
            queue: $view['queue'] ?? $defaults['queue'],
            chunk: $view['chunk'] ?? $defaults['chunk'],
            lockSeconds: $view['lock_seconds'] ?? $defaults['lock_seconds'],
            tries: $view['tries'] ?? $defaults['tries'],
            timeout: $view['timeout'] ?? $defaults['timeout'],
            branchScoped: $view['branch_scoped'] ?? false,
            enabled: $view['enabled'] ?? true,
        );
    }

    public function builder(): MaterializedViewBuilder
    {
        return app($this->builderClass);
    }

    public function store(): RollupStore
    {
        return app($this->storeClass);
    }
}
