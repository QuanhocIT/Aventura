<?php

namespace Tests\Feature\Smoke;

use App\Http\Middleware\SecurityFirewallMiddleware;
use App\Http\Middleware\TenantRateLimit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Smoke-tests the order mutation actions (tách bill / gộp bill / chuyển bàn) that
 * the GET crawler can't reach. Business-rule rejections surface as 422
 * (ValidationException) — acceptable; only a 5xx (schema drift, undefined method,
 * bad column) fails the test. Runs against the aventura_test clone; all writes
 * roll back via DatabaseTransactions.
 *
 *   php artisan test tests/Feature/Smoke/OrderActionsSmokeTest.php -c phpunit.mysql.xml
 */
class OrderActionsSmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            SecurityFirewallMiddleware::class,
            TenantRateLimit::class,
        ]);
    }

    public function test_split_merge_move_table_do_not_500(): void
    {
        $fixture = $this->resolveFixture();
        if (! $fixture) {
            $this->markTestSkipped('Không tìm thấy nhà hàng có owner + ≥2 đơn pending có món để test.');
        }

        [$owner, $splitOrderId, $mergeTargetId, $itemId, $tableId] = $fixture;

        $split = $this->actingAs($owner)->post("/orders/{$splitOrderId}/split", [
            'item_ids' => [$itemId],
        ]);
        $this->assertLessThan(500, $split->getStatusCode(),
            'split 5xx: '.optional($split->exception)->getMessage());

        if ($tableId) {
            $move = $this->actingAs($owner)->post("/orders/{$splitOrderId}/move-table", [
                'table_id' => $tableId,
            ]);
            $this->assertLessThan(500, $move->getStatusCode(),
                'move-table 5xx: '.optional($move->exception)->getMessage());
        }

        $merge = $this->actingAs($owner)->post("/orders/{$mergeTargetId}/merge", [
            'target_order_id' => $splitOrderId,
        ]);
        $this->assertLessThan(500, $merge->getStatusCode(),
            'merge 5xx: '.optional($merge->exception)->getMessage());
    }

    /**
     * @return array{0:User,1:int,2:int,3:int,4:?int}|null
     */
    private function resolveFixture(): ?array
    {
        $owners = User::whereHas('roles', fn ($q) => $q->where('name', 'owner'))
            ->whereNotNull('restaurant_id')->get();

        foreach ($owners as $owner) {
            $rid = (int) $owner->restaurant_id;

            // Đơn pending có ít nhất 1 món (an toàn cho tách/gộp/chuyển bàn).
            $orders = DB::table('orders')
                ->where('restaurant_id', $rid)
                ->where('status', 'pending')
                ->whereRaw('(SELECT COUNT(*) FROM order_items WHERE order_id = orders.id) >= 1')
                ->orderByDesc('id')->limit(5)->pluck('id')->all();

            if (count($orders) < 2) {
                continue;
            }

            $splitOrderId = (int) $orders[0];
            $mergeTargetId = (int) $orders[1];

            $itemId = (int) DB::table('order_items')->where('order_id', $splitOrderId)->value('id');
            if (! $itemId) {
                continue;
            }

            $tableId = DB::table('restaurant_tables')->where('restaurant_id', $rid)->value('id');

            return [$owner, $splitOrderId, $mergeTargetId, $itemId, $tableId ? (int) $tableId : null];
        }

        return null;
    }
}
