<?php

namespace App\Support\MaterializedViews;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Đọc config/materialized_views.php, hydrate thành các MaterializedView.
 * Bind singleton trong AppServiceProvider::register() cạnh OrderRepositoryInterface.
 */
class MaterializedViewRegistry
{
    /** @var Collection<string, MaterializedView> */
    private Collection $views;

    public function __construct()
    {
        $defaults = config('materialized_views.defaults', []);

        $this->views = collect(config('materialized_views.views', []))
            ->map(fn (array $view, string $name) => MaterializedView::fromConfig($name, $view, $defaults));
    }

    /** @return Collection<string, MaterializedView> */
    public function all(): Collection
    {
        return $this->views;
    }

    /** @return Collection<string, MaterializedView> */
    public function enabled(): Collection
    {
        return $this->views->filter(fn (MaterializedView $v) => $v->enabled);
    }

    public function get(string $name): MaterializedView
    {
        $view = $this->views->get($name);

        if (! $view) {
            throw new InvalidArgumentException("Materialized view [{$name}] chưa được khai báo trong config/materialized_views.php");
        }

        return $view;
    }

    public function has(string $name): bool
    {
        return $this->views->has($name);
    }
}
