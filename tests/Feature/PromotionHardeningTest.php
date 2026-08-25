<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bao phủ các lỗi đã được vá trên màn hình Khuyến mãi:
 * ngày tháng của form sửa, ràng buộc voucher tiền mặt, trạng thái vận hành,
 * gate quyền của API áp mã, giới hạn lượt dùng và phạm vi chi nhánh.
 */
class PromotionHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private User $kitchen;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Bộ đếm chống gian lận thời gian thực sống trong cache và KHÔNG bị
        // RefreshDatabase dọn; vì ID người dùng lặp lại giữa các test nên bộ đếm
        // của test trước sẽ kích hoạt nhầm yêu cầu mã bypass ở test sau.
        Cache::flush();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);

        foreach (['manage_orders', 'view_report', 'manage_kitchen', 'create_orders', 'process_payments'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $managerRole->givePermissionTo(['manage_orders', 'view_report']);
        $kitchenRole->givePermissionTo(['manage_kitchen']);

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['branch_id' => $this->branch->id]);

        app(TenantContext::class)->setRestaurantId($this->restaurant->id);

        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->manager->assignRole($managerRole);
        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->manager->id,
            'role_id' => $managerRole->id,
            'status' => 'active',
        ]);

        $this->kitchen = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->kitchen->assignRole($kitchenRole);
        $kitchenEmp = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->kitchen->id,
            'role_id' => $kitchenRole->id,
            'status' => 'active',
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Ngay',
            'code' => 'CA_NGAY',
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'is_overnight' => false,
            'status' => 'active',
        ]);

        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $kitchenEmp->id,
            'shift_id' => $shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);
    }

    private function makePromotion(array $attributes = []): Promotion
    {
        return Promotion::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => null,
            'name' => 'Ưu đãi test',
            'code' => 'TEST10',
            'type' => 'percent',
            'value' => 10,
            'min_order_amount' => 0,
            'max_discount_amount' => 0,
            'is_active' => true,
            'is_approved' => true,
            'created_by' => $this->owner->id,
            'approved_by' => $this->owner->id,
        ], $attributes));
    }

    private function makeOrder(float $subtotal = 500000, bool $withCustomer = true): Order
    {
        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'price' => $subtotal,
        ]);

        $order = Order::factory()->create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'discount_amount' => 0,
        ], $withCustomer ? [] : ['customer_id' => null]));

        $order->items()->create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $subtotal,
            'line_total' => $subtotal,
        ]);

        return $order->fresh('items');
    }

    // ── Ngày tháng của form sửa ────────────────────────────────────────────

    public function test_index_exposes_datetime_local_compatible_dates(): void
    {
        $this->makePromotion([
            'start_date' => '2026-07-18 00:00:00',
            'end_date' => '2026-08-17 00:00:00',
        ]);

        $response = $this->actingAs($this->owner)->get('/promotions');
        $response->assertStatus(200);

        $promotion = $response->viewData('page')['props']['promotions'][0];

        // Bản hiển thị giữ nguyên định dạng Việt Nam...
        $this->assertSame('18/07/2026 00:00', $promotion['start_date']);
        // ...còn bản dành cho <input type="datetime-local"> phải là ISO.
        $this->assertSame('2026-07-18T00:00', $promotion['start_date_input']);
        $this->assertSame('2026-08-17T00:00', $promotion['end_date_input']);
    }

    public function test_update_accepts_the_date_format_the_form_sends_back(): void
    {
        $promotion = $this->makePromotion([
            'start_date' => '2026-07-18 00:00:00',
            'end_date' => '2026-08-17 00:00:00',
        ]);

        $this->actingAs($this->owner)
            ->put("/promotions/{$promotion->id}", [
                'name' => 'Đã đổi tên',
                'code' => 'TEST10',
                'type' => 'percent',
                'value' => 10,
                'min_order_amount' => 0,
                'start_date' => '2026-07-18T00:00',
                'end_date' => '2026-08-17T00:00',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Đã đổi tên', $promotion->fresh()->name);
    }

    public function test_update_rejects_the_old_display_date_format(): void
    {
        $promotion = $this->makePromotion();

        $this->actingAs($this->owner)
            ->put("/promotions/{$promotion->id}", [
                'name' => 'Không nên lưu',
                'type' => 'percent',
                'value' => 10,
                'start_date' => '18/07/2026 00:00',
            ])
            ->assertSessionHasErrors('start_date');
    }

    // ── Ràng buộc voucher khấu trừ tiền mặt ────────────────────────────────

    public function test_fixed_amount_voucher_requires_a_minimum_order(): void
    {
        $this->actingAs($this->owner)
            ->post('/promotions', [
                'name' => 'Giảm 50k',
                'code' => 'CASH50',
                'type' => 'fixed_amount',
                'value' => 50000,
            ])
            ->assertSessionHasErrors('min_order_amount');
    }

    public function test_fixed_amount_voucher_cannot_zero_out_the_order(): void
    {
        // value == min_order: luật cũ cho qua và đơn về 0đ.
        $this->actingAs($this->owner)
            ->post('/promotions', [
                'name' => 'Miễn phí trá hình',
                'code' => 'FREE',
                'type' => 'fixed_amount',
                'value' => 250000,
                'min_order_amount' => 250000,
            ])
            ->assertSessionHasErrors('value');
    }

    public function test_fixed_amount_voucher_is_creatable_with_a_sane_shape(): void
    {
        $this->actingAs($this->owner)
            ->post('/promotions', [
                'name' => 'Giảm 50k cho đơn từ 250k',
                'code' => 'CASH50',
                'type' => 'fixed_amount',
                'value' => 50000,
                'min_order_amount' => 250000,
                'max_discount_amount' => 999999,
            ])
            ->assertSessionHasNoErrors();

        $promotion = Promotion::where('code', 'CASH50')->first();
        $this->assertNotNull($promotion);
        // max_discount_amount vô nghĩa với loại tiền mặt nên phải bị triệt tiêu,
        // không để lại con số rác hiển thị trên bảng.
        $this->assertSame(0.0, (float) $promotion->max_discount_amount);
    }

    // ── Trạng thái vận hành ────────────────────────────────────────────────

    public function test_operational_status_reflects_expiry_not_just_the_active_flag(): void
    {
        $expired = $this->makePromotion([
            'code' => 'EXPIRED',
            'start_date' => now()->subDays(30),
            'end_date' => now()->subDays(7),
        ]);

        // Cờ is_active vẫn đang bật vì cron chỉ chạy mỗi ngày một lần.
        $this->assertTrue($expired->is_active);
        $this->assertSame(Promotion::STATUS_EXPIRED, $expired->operationalStatus());

        $scheduled = $this->makePromotion([
            'code' => 'SOON',
            'start_date' => now()->addDays(3),
        ]);
        $this->assertSame(Promotion::STATUS_SCHEDULED, $scheduled->operationalStatus());

        $pending = $this->makePromotion(['code' => 'WAIT', 'is_approved' => false]);
        $this->assertSame(Promotion::STATUS_PENDING, $pending->operationalStatus());

        $running = $this->makePromotion(['code' => 'LIVE']);
        $this->assertSame(Promotion::STATUS_RUNNING, $running->operationalStatus());
    }

    public function test_cannot_activate_an_expired_or_unapproved_promotion(): void
    {
        $expired = $this->makePromotion([
            'code' => 'EXPIRED',
            'is_active' => false,
            'end_date' => now()->subDay(),
        ]);

        $this->actingAs($this->owner)
            ->patch("/promotions/{$expired->id}/toggle")
            ->assertSessionHasErrors('is_active');

        $this->assertFalse($expired->fresh()->is_active);

        $unapproved = $this->makePromotion([
            'code' => 'WAIT',
            'is_active' => false,
            'is_approved' => false,
        ]);

        $this->actingAs($this->owner)
            ->patch("/promotions/{$unapproved->id}/toggle")
            ->assertSessionHasErrors('is_active');
    }

    // ── Gate quyền của API áp mã ───────────────────────────────────────────

    public function test_kitchen_staff_cannot_apply_a_voucher_to_an_order(): void
    {
        $this->makePromotion(['code' => 'TEST10', 'min_order_amount' => 0]);
        $order = $this->makeOrder();

        $this->actingAs($this->kitchen)
            ->postJson('/api/promotions/apply', [
                'order_id' => $order->id,
                'code' => 'TEST10',
            ])
            ->assertStatus(403);

        $this->assertSame(0.0, (float) $order->fresh()->discount_amount);
    }

    // ── Ghi vết lượt dùng & giới hạn lượt ──────────────────────────────────

    public function test_applying_a_voucher_records_a_usage_row(): void
    {
        $promotion = $this->makePromotion(['code' => 'TEST10', 'min_order_amount' => 0]);
        $order = $this->makeOrder(500000);

        $this->actingAs($this->manager)
            ->postJson('/api/promotions/apply', [
                'order_id' => $order->id,
                'code' => 'TEST10',
            ])
            ->assertOk();

        $usage = PromotionUsage::withoutGlobalScopes()
            ->where('promotion_id', $promotion->id)
            ->where('order_id', $order->id)
            ->first();

        $this->assertNotNull($usage);
        $this->assertSame(50000.0, (float) $usage->discount_amount);
        $this->assertSame(500000.0, (float) $usage->order_subtotal);
    }

    public function test_the_same_voucher_cannot_be_applied_twice_even_if_the_note_is_edited(): void
    {
        $this->makePromotion(['code' => 'TEST10', 'min_order_amount' => 0]);
        // Đơn khách vãng lai: tách khỏi quy tắc "cùng khách được giảm giá trong
        // 10 phút" để bài test cô lập đúng cơ chế chống áp trùng mã.
        $order = $this->makeOrder(500000, withCustomer: false);

        $this->actingAs($this->manager)
            ->postJson('/api/promotions/apply', ['order_id' => $order->id, 'code' => 'TEST10'])
            ->assertOk();

        // Cách chống trùng cũ dò chuỗi trong note — xoá note là bypass được.
        $order->fresh()->update(['note' => null]);

        $this->actingAs($this->manager)
            ->postJson('/api/promotions/apply', ['order_id' => $order->id, 'code' => 'TEST10'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Mã khuyến mãi này đã được áp dụng cho đơn hàng.']);
    }

    public function test_usage_limit_blocks_further_applications(): void
    {
        $promotion = $this->makePromotion([
            'code' => 'TEST10',
            'min_order_amount' => 0,
            'usage_limit' => 1,
        ]);

        $firstOrder = $this->makeOrder(500000);
        $this->actingAs($this->manager)
            ->postJson('/api/promotions/apply', ['order_id' => $firstOrder->id, 'code' => 'TEST10'])
            ->assertOk();

        $secondOrder = $this->makeOrder(500000);
        $this->actingAs($this->manager)
            ->postJson('/api/promotions/apply', ['order_id' => $secondOrder->id, 'code' => 'TEST10'])
            ->assertStatus(422);

        $this->assertFalse($promotion->fresh()->is_active);
    }

    // ── Phạm vi chi nhánh ──────────────────────────────────────────────────

    public function test_branch_scope_is_explicit_not_inherited_from_the_data_scope_selector(): void
    {
        // Giả lập người dùng đang xem một chi nhánh cụ thể ở thanh phạm vi dữ liệu.
        app(TenantContext::class)->setActiveBranchId($this->branch->id);

        $this->actingAs($this->owner)
            ->post('/promotions', [
                'name' => 'Mã toàn chuỗi',
                'code' => 'CHAIN',
                'type' => 'percent',
                'value' => 10,
                'branch_id' => null,
            ])
            ->assertSessionHasNoErrors();

        // Trước đây trait BelongsToRestaurant tự gán branch_id của chi nhánh
        // đang active, biến mã "toàn chuỗi" thành mã của riêng một chi nhánh.
        $this->assertNull(Promotion::where('code', 'CHAIN')->first()->branch_id);
    }

    // ── Xoá an toàn ────────────────────────────────────────────────────────

    public function test_a_promotion_with_usage_history_cannot_be_hard_deleted(): void
    {
        $promotion = $this->makePromotion(['code' => 'TEST10', 'min_order_amount' => 0]);
        $order = $this->makeOrder(500000);

        $this->actingAs($this->manager)
            ->postJson('/api/promotions/apply', ['order_id' => $order->id, 'code' => 'TEST10'])
            ->assertOk();

        $this->actingAs($this->owner)
            ->delete("/promotions/{$promotion->id}")
            ->assertSessionHasErrors('delete');

        $this->assertNotNull($promotion->fresh());
    }

    // ── Điều kiện áp dụng (Happy hour) ─────────────────────────────────────

    public function test_conditions_are_persisted_and_stripped_of_empty_values(): void
    {
        $this->actingAs($this->owner)
            ->post('/promotions', [
                'name' => 'Happy hour chiều',
                'code' => 'HAPPY',
                'type' => 'percent',
                'value' => 20,
                'conditions' => [
                    'day_of_week' => [3, 1, 1],
                    'time_range' => ['start' => '14:00', 'end' => '17:00'],
                    'min_items' => null,
                    'first_order_only' => false,
                ],
            ])
            ->assertSessionHasNoErrors();

        $conditions = Promotion::where('code', 'HAPPY')->first()->conditions;

        // Trùng lặp bị loại, đã sắp xếp.
        $this->assertSame([1, 3], $conditions['day_of_week']);
        $this->assertSame(['start' => '14:00', 'end' => '17:00'], $conditions['time_range']);
        // Giá trị rỗng bị loại hẳn thay vì lưu null/false gây hiểu nhầm.
        $this->assertArrayNotHasKey('min_items', $conditions);
        $this->assertArrayNotHasKey('first_order_only', $conditions);
    }

    public function test_conditions_reject_malformed_payloads(): void
    {
        $this->actingAs($this->owner)
            ->post('/promotions', [
                'name' => 'Sai điều kiện',
                'type' => 'percent',
                'value' => 20,
                'conditions' => [
                    'day_of_week' => [9],
                    'time_range' => ['start' => '25h', 'end' => '17:00'],
                ],
            ])
            ->assertSessionHasErrors([
                'conditions.day_of_week.0',
                'conditions.time_range.start',
            ]);
    }

    public function test_empty_conditions_are_stored_as_null(): void
    {
        $this->actingAs($this->owner)
            ->post('/promotions', [
                'name' => 'Không điều kiện',
                'code' => 'PLAIN',
                'type' => 'percent',
                'value' => 20,
                'conditions' => [
                    'day_of_week' => [],
                    'time_range' => null,
                    'min_items' => null,
                    'first_order_only' => false,
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(Promotion::where('code', 'PLAIN')->first()->conditions);
    }

    // ── Ngân sách & stacking thực sự được lưu ──────────────────────────────

    public function test_update_persists_budget_and_stacking_fields(): void
    {
        // update() từng validate các trường này nhưng không đưa vào mảng update.
        $promotion = $this->makePromotion(['code' => 'TEST10']);

        $this->actingAs($this->owner)
            ->put("/promotions/{$promotion->id}", [
                'name' => 'Có ngân sách',
                'code' => 'TEST10',
                'type' => 'percent',
                'value' => 10,
                'budget_cap' => 5000000,
                'auto_deactivate_on_budget' => true,
                'usage_limit' => 100,
                'is_stackable' => true,
                'stacking_priority' => 5,
                'stacking_group' => 'khai-truong',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $promotion->fresh();
        $this->assertSame(5000000.0, (float) $fresh->budget_cap);
        $this->assertTrue($fresh->auto_deactivate_on_budget);
        $this->assertSame(100, $fresh->usage_limit);
        $this->assertTrue($fresh->is_stackable);
        $this->assertSame(5, (int) $fresh->stacking_priority);
        $this->assertSame('khai-truong', $fresh->stacking_group);
    }

    public function test_budget_cap_cannot_be_lowered_below_what_was_already_spent(): void
    {
        $promotion = $this->makePromotion([
            'code' => 'TEST10',
            'budget_cap' => 1000000,
            'budget_spent' => 400000,
        ]);

        $this->actingAs($this->owner)
            ->put("/promotions/{$promotion->id}", [
                'name' => 'Hạ ngân sách',
                'code' => 'TEST10',
                'type' => 'percent',
                'value' => 10,
                'budget_cap' => 100000,
            ])
            ->assertSessionHasErrors('budget_cap');
    }

    // ── Summary phục vụ banner nhắc việc ───────────────────────────────────

    public function test_summary_counts_stay_stable_when_a_status_filter_is_applied(): void
    {
        $this->makePromotion(['code' => 'LIVE']);
        $this->makePromotion([
            'code' => 'DEAD',
            'end_date' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->owner)->get('/promotions?status=running');
        $props = $response->viewData('page')['props'];

        // Danh sách bị lọc còn 1 dòng...
        $this->assertCount(1, $props['promotions']);
        // ...nhưng summary vẫn đếm trên toàn bộ, nếu không banner sẽ báo 0.
        $this->assertSame(1, $props['summary']['expired']);
        $this->assertSame(2, $props['summary']['total']);
    }

    // ── Trần tổng giảm giá khi cộng dồn với loyalty ────────────────────────

    public function test_voucher_is_clamped_by_the_total_discount_ceiling(): void
    {
        config(['promotions.max_total_discount_percent' => 50]);

        $this->makePromotion(['code' => 'TEST10', 'value' => 40, 'min_order_amount' => 0]);
        $order = $this->makeOrder(1000000, withCustomer: false);

        // Ưu đãi hội viên đã giảm sẵn 30% trước khi voucher được áp.
        $order->update(['discount_amount' => 300000, 'total_amount' => 700000]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/promotions/apply', ['order_id' => $order->id, 'code' => 'TEST10'])
            ->assertOk();

        // Voucher 40% = 400.000đ, nhưng trần 50% chỉ còn chỗ cho 200.000đ.
        $this->assertSame(200000.0, (float) $response->json('discount_amount'));
        $this->assertSame(500000.0, (float) $order->fresh()->discount_amount);
        $this->assertSame(500000.0, (float) $order->fresh()->total_amount);
    }

    public function test_voucher_is_refused_when_the_ceiling_is_already_reached(): void
    {
        config(['promotions.max_total_discount_percent' => 50]);

        $this->makePromotion(['code' => 'TEST10', 'min_order_amount' => 0]);
        $order = $this->makeOrder(1000000, withCustomer: false);
        $order->update(['discount_amount' => 500000, 'total_amount' => 500000]);

        $this->actingAs($this->manager)
            ->postJson('/api/promotions/apply', ['order_id' => $order->id, 'code' => 'TEST10'])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Đơn hàng đã đạt mức giảm giá tối đa cho phép (50%). Không thể áp thêm mã.',
            ]);
    }

    public function test_high_stacked_discount_returns_a_warning(): void
    {
        config([
            'promotions.max_total_discount_percent' => 100,
            'promotions.warn_total_discount_percent' => 50,
        ]);

        $this->makePromotion(['code' => 'TEST10', 'value' => 60, 'min_order_amount' => 0]);
        $order = $this->makeOrder(1000000, withCustomer: false);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/promotions/apply', ['order_id' => $order->id, 'code' => 'TEST10'])
            ->assertOk();

        $this->assertNotNull($response->json('warning'));
        $this->assertSame(60.0, (float) $response->json('total_discount_percent'));

        // Có lưu vết để đối soát về sau.
        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $this->restaurant->id,
            'action' => 'discount_stacked_high',
        ]);
    }

    public function test_no_warning_below_the_threshold(): void
    {
        config(['promotions.warn_total_discount_percent' => 50]);

        $this->makePromotion(['code' => 'TEST10', 'value' => 10, 'min_order_amount' => 0]);
        $order = $this->makeOrder(1000000, withCustomer: false);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/promotions/apply', ['order_id' => $order->id, 'code' => 'TEST10'])
            ->assertOk();

        $this->assertNull($response->json('warning'));
    }

    // ── Lọc chi nhánh & phân trang ─────────────────────────────────────────

    public function test_branch_filter_narrows_the_list(): void
    {
        $this->makePromotion(['code' => 'CHAIN', 'branch_id' => null]);
        $this->makePromotion(['code' => 'LOCAL', 'branch_id' => $this->branch->id]);

        $chainOnly = $this->actingAs($this->owner)->get('/promotions?branch=chain');
        $props = $chainOnly->viewData('page')['props'];
        $this->assertCount(1, $props['promotions']);
        $this->assertSame('CHAIN', $props['promotions'][0]['code']);

        $branchOnly = $this->actingAs($this->owner)->get("/promotions?branch={$this->branch->id}");
        $props = $branchOnly->viewData('page')['props'];
        $this->assertCount(1, $props['promotions']);
        $this->assertSame('LOCAL', $props['promotions'][0]['code']);
    }

    public function test_promotion_list_is_paginated(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->makePromotion(['code' => 'CODE'.$i, 'name' => 'Ưu đãi '.$i]);
        }

        $response = $this->actingAs($this->owner)->get('/promotions');
        $props = $response->viewData('page')['props'];

        $this->assertCount(20, $props['promotions']);
        $this->assertSame(25, $props['pagination']['total']);
        $this->assertSame(2, $props['pagination']['last_page']);

        $page2 = $this->actingAs($this->owner)->get('/promotions?page=2');
        $this->assertCount(5, $page2->viewData('page')['props']['promotions']);
    }

    // ── Endpoint xem trước mã ──────────────────────────────────────────────

    public function test_validate_endpoint_returns_usage_data_for_preview(): void
    {
        $this->makePromotion([
            'code' => 'TEST10',
            'min_order_amount' => 0,
            'usage_limit' => 50,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/promotions/validate', ['code' => 'TEST10'])
            ->assertOk();

        $this->assertTrue($response->json('valid'));
        $this->assertSame(0, $response->json('promotion.usage_count'));
        $this->assertSame(50, $response->json('promotion.usage_limit'));
    }

    public function test_validate_endpoint_is_gated(): void
    {
        $this->makePromotion(['code' => 'TEST10']);

        $this->actingAs($this->kitchen)
            ->postJson('/api/promotions/validate', ['code' => 'TEST10'])
            ->assertStatus(403);
    }

    // ── Tạo combo nhanh từ gợi ý AI ────────────────────────────────────────

    /** @return array{0: Product, 1: Product, 2: Ingredient} */
    private function makeTwoDishesSharingAnIngredient(): array
    {
        $kg = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);

        $shared = Ingredient::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $kg->id,
            'name' => 'Dầu ăn',
        ]);

        $dishA = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Gà rán',
            'price' => 100000,
        ]);
        $dishB = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Khoai tây chiên',
            'price' => 50000,
        ]);

        // Cùng một nguyên liệu xuất hiện trong CẢ HAI công thức.
        foreach ([[$dishA, 0.2], [$dishB, 0.3]] as [$dish, $qty]) {
            ProductRecipe::create([
                'restaurant_id' => $this->restaurant->id,
                'product_id' => $dish->id,
                'ingredient_id' => $shared->id,
                'unit_id' => $kg->id,
                'quantity' => $qty,
                'waste_rate' => 0,
            ]);
        }

        return [$dishA, $dishB, $shared];
    }

    public function test_combo_creation_merges_a_shared_ingredient_instead_of_crashing(): void
    {
        [$dishA, $dishB, $shared] = $this->makeTwoDishesSharingAnIngredient();

        // product_recipes có unique(product_id, ingredient_id): trước đây lần
        // insert thứ hai văng 500 và để lại món combo rác trong thực đơn.
        $this->actingAs($this->owner)
            ->post('/promotions/combos', [
                'name' => 'Combo gà + khoai',
                'item_a_id' => $dishA->id,
                'item_b_id' => $dishB->id,
                'combo_price' => 130000,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $combo = Product::where('name', 'Combo gà + khoai')->first();
        $this->assertNotNull($combo);

        $recipes = ProductRecipe::where('product_id', $combo->id)
            ->where('ingredient_id', $shared->id)
            ->get();

        // Đúng MỘT dòng, và định lượng là tổng của hai công thức.
        $this->assertCount(1, $recipes);
        $this->assertEqualsWithDelta(0.5, (float) $recipes->first()->quantity, 0.001);
    }

    public function test_failed_combo_creation_leaves_no_orphan_product(): void
    {
        [$dishA, $dishB] = $this->makeTwoDishesSharingAnIngredient();

        // Giá combo không rẻ hơn tổng giá lẻ -> bị từ chối.
        $this->actingAs($this->owner)
            ->post('/promotions/combos', [
                'name' => 'Combo hỏng',
                'item_a_id' => $dishA->id,
                'item_b_id' => $dishB->id,
                'combo_price' => 200000,
            ])
            ->assertSessionHasErrors('combo_price');

        $this->assertNull(Product::where('name', 'Combo hỏng')->first());
    }

    // ── In phiếu QR sau khi có phân trang ──────────────────────────────────

    public function test_printable_ids_cover_every_filtered_promotion_not_just_the_page(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->makePromotion(['code' => 'CODE'.$i, 'name' => 'Ưu đãi '.$i]);
        }

        $props = $this->actingAs($this->owner)->get('/promotions')->viewData('page')['props'];

        $this->assertCount(20, $props['promotions']);
        // Nút "In phiếu QR" phải in cả 25 mã, không chỉ 20 dòng đang hiển thị.
        $this->assertCount(25, $props['printableIds']);
    }

    // ── Danh sách mã cho thu ngân ──────────────────────────────────────────

    public function test_available_list_hides_a_voucher_the_customer_already_used_up(): void
    {
        $promotion = $this->makePromotion([
            'code' => 'ONCE',
            'min_order_amount' => 0,
            'usage_limit_per_customer' => 1,
        ]);

        $order = $this->makeOrder(500000);

        PromotionUsage::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'promotion_id' => $promotion->id,
            'order_id' => $this->makeOrder(100000)->id,
            'customer_id' => $order->customer_id,
            'applied_by' => $this->owner->id,
            'discount_amount' => 10000,
            'order_subtotal' => 100000,
        ]);

        // Dùng tài khoản cấp chi nhánh: SetTenantContext suy ra chi nhánh đang
        // hoạt động từ user, còn Owner mặc định ở phạm vi toàn chuỗi nên POS
        // endpoint từ chối (422).
        $response = $this->actingAs($this->manager)
            ->getJson('/api/promotions/available?order_id='.$order->id)
            ->assertOk();

        // Trước đây mã vẫn hiện trong dropdown rồi mới báo lỗi lúc bấm Áp dụng.
        $this->assertSame([], $response->json('promotions'));
    }
}
