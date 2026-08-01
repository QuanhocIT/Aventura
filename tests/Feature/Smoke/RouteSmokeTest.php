<?php

namespace Tests\Feature\Smoke;

use App\Http\Middleware\SecurityFirewallMiddleware;
use App\Http\Middleware\TenantRateLimit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Runs against a throwaway MySQL clone (aventura_test) via phpunit.mysql.xml.
 * Crawls every GET route as an owner and as a super-admin, resolving {params}
 * from real seeded rows, and records any page that returns HTTP >= 500.
 *
 *   php artisan test --configuration=phpunit.mysql.xml
 *
 * Report is written to storage/logs/smoke-report.txt. The test FAILS if any
 * route 500s, so it doubles as a regression net for MySQL schema-drift bugs.
 */
class RouteSmokeTest extends TestCase
{
    /** URI prefixes that are framework/dev/binary/webhook noise, not app pages. */
    private array $skipPrefixes = [
        'telescope', 'horizon', 'pulse', '_ignition', '_debugbar', 'sanctum',
        'livewire', 'storage', 'build', 'up', 'broadcasting', 'api/webhooks',
        'api/pos', 'sitemap.xml', 'oauth',
    ];

    /** Param name (singular) => table used to look up a real id. */
    private array $paramTable = [
        'restaurant' => 'restaurants', 'order' => 'orders', 'product' => 'products',
        'category' => 'categories', 'employee' => 'employees', 'customer' => 'customers',
        'user' => 'users', 'ingredient' => 'ingredients', 'recipe' => 'recipes',
        'purchase' => 'purchases', 'reservation' => 'reservations', 'promotion' => 'promotions',
        'coupon' => 'coupons', 'plan' => 'subscription_plans', 'note' => 'restaurant_notes',
        'tag' => 'restaurant_tags', 'followup' => 'restaurant_followups', 'post' => 'news_posts',
        'banner' => 'banners', 'ticket' => 'support_tickets', 'expense' => 'expenses',
        'debt' => 'debts', 'schedule' => 'schedules', 'shift' => 'shifts', 'leave' => 'leave_requests',
        'campaign' => 'campaigns', 'report' => 'reports', 'table' => 'restaurant_tables',
        'area' => 'areas', 'supplier' => 'suppliers', 'equipment' => 'equipment',
        'violation' => 'violation_reports', 'goal' => 'business_goals',
    ];

    public function test_all_get_routes_do_not_500(): void
    {
        // Firewall + rate limiter would 429 the crawl before controllers run,
        // masking real page errors. Auth / tenant-context / permission stay on.
        $this->withoutMiddleware([
            SecurityFirewallMiddleware::class,
            TenantRateLimit::class,
        ]);

        $owner = User::whereHas('roles', fn ($q) => $q->where('name', 'owner'))
            ->whereNotNull('restaurant_id')->orderBy('id')->first();
        $superAdmin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->orderBy('id')->first();

        $this->assertNotNull($owner, 'No owner user in aventura_test');
        $this->assertNotNull($superAdmin, 'No super_admin user in aventura_test');

        $failures = [];
        $lines = [];
        $counts = ['ok' => 0, 'redirect' => 0, 'client' => 0, 'skipped' => 0, 'fail' => 0];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $uri = $route->uri();

            if ($this->shouldSkip($uri)) {
                $counts['skipped']++;

                continue;
            }

            $isSuperAdmin = str_starts_with($uri, 'super-admin');
            $actor = $isSuperAdmin ? $superAdmin : $owner;

            [$url, $resolvable] = $this->buildUrl($route, $actor);
            if (! $resolvable) {
                $counts['skipped']++;
                $lines[] = sprintf('SKIP  (unresolved param)  %s', $uri);

                continue;
            }

            try {
                $response = $this->actingAs($actor)->get($url);
                $status = $response->getStatusCode();
            } catch (\Throwable $e) {
                $status = 500;
                $response = null;
                $exceptionMessage = $e->getMessage();
            }

            if ($status >= 500) {
                $counts['fail']++;
                $msg = $exceptionMessage
                    ?? optional($response->exception ?? null)->getMessage()
                    ?? 'unknown';
                $failures[] = $uri;
                $lines[] = sprintf("FAIL  %d  %-55s  as=%s\n        %s",
                    $status, $uri, $isSuperAdmin ? 'super_admin' : 'owner', $this->oneLine($msg));
            } elseif ($status >= 400) {
                $counts['client']++;
                $lines[] = sprintf('%d  %-55s  as=%s', $status, $uri, $isSuperAdmin ? 'super_admin' : 'owner');
            } elseif ($status >= 300) {
                $counts['redirect']++;
                $lines[] = sprintf('%d  %-55s  as=%s', $status, $uri, $isSuperAdmin ? 'super_admin' : 'owner');
            } else {
                $counts['ok']++;
                $lines[] = sprintf('%d  %-55s  as=%s', $status, $uri, $isSuperAdmin ? 'super_admin' : 'owner');
            }
            $exceptionMessage = null;
        }

        $header = sprintf(
            "SMOKE REPORT %s\nOK=%d  Redirect=%d  Client(4xx)=%d  Skipped=%d  FAIL(5xx)=%d\n%s\n",
            now()->toDateTimeString(),
            $counts['ok'], $counts['redirect'], $counts['client'], $counts['skipped'], $counts['fail'],
            str_repeat('=', 80)
        );
        // Failures first for quick scanning.
        sort($lines);
        $failLines = array_values(array_filter($lines, fn ($l) => str_starts_with($l, 'FAIL')));
        file_put_contents(
            storage_path('logs/smoke-report.txt'),
            $header.implode("\n", $failLines)."\n".str_repeat('-', 80)."\n".implode("\n", $lines)."\n"
        );

        $this->assertSame(
            [],
            $failures,
            sprintf("%d route(s) returned 5xx. See storage/logs/smoke-report.txt\n%s",
                count($failures), implode("\n", $failLines))
        );
    }

    private function shouldSkip(string $uri): bool
    {
        foreach ($this->skipPrefixes as $p) {
            if ($uri === $p || str_starts_with($uri, $p.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0:string,1:bool} [url, resolvable] */
    private function buildUrl($route, User $actor): array
    {
        $uri = $route->uri();
        if (! str_contains($uri, '{')) {
            return ['/'.ltrim($uri, '/'), true];
        }

        $resolvable = true;
        $url = preg_replace_callback('/\{([^}]+)\}/', function ($m) use (&$resolvable, $actor) {
            $raw = rtrim($m[1], '?');
            $name = Str::snake($raw);
            $singular = Str::singular($name);
            $table = $this->paramTable[$singular] ?? null;

            if (! $table) {
                // Generic guess: pluralize the param name.
                $guess = Str::plural($name);
                if ($this->tableExists($guess)) {
                    $table = $guess;
                }
            }
            if (! $table || ! $this->tableExists($table)) {
                $resolvable = false;

                return '0';
            }
            $id = $this->firstId($table, $actor->restaurant_id);
            if ($id === null) {
                $resolvable = false;

                return '0';
            }

            return (string) $id;
        }, $uri);

        return ['/'.ltrim($url, '/'), $resolvable];
    }

    private array $tableCache = [];

    private function tableExists(string $table): bool
    {
        return $this->tableCache[$table] ??= DB::getSchemaBuilder()->hasTable($table);
    }

    private function firstId(string $table, ?int $restaurantId): ?int
    {
        $q = DB::table($table);
        if ($restaurantId && DB::getSchemaBuilder()->hasColumn($table, 'restaurant_id')) {
            $scoped = (clone $q)->where('restaurant_id', $restaurantId)->orderBy('id')->value('id');
            if ($scoped !== null) {
                return (int) $scoped;
            }
        }
        $any = $q->orderBy('id')->value('id');

        return $any !== null ? (int) $any : null;
    }

    private function oneLine(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::limit($s, 300)));
    }
}
