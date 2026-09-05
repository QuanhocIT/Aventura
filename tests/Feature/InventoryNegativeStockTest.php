<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryNegativeCase;
use App\Models\InventoryNegativeCaseEvent;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\NegativeInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryNegativeStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_balance_creates_case_plan_and_closes_after_restock_review(): void
    {
        [$owner, $restaurant, $branch, $ingredient] = $this->inventoryFixture();
        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 2,
            'theoretical_quantity' => 2,
            'last_cost' => 100,
        ]);

        $inventory->update([
            'quantity_on_hand' => -3,
            'theoretical_quantity' => -3,
        ]);

        $case = InventoryNegativeCase::withoutGlobalScopes()
            ->where('inventory_id', $inventory->id)
            ->where('status', 'open')
            ->firstOrFail();
        $this->assertSame(3.0, (float) $case->negative_quantity);
        $this->assertStringContainsString('kiểm kê', (string) $case->auto_plan);

        $service = app(NegativeInventoryService::class);
        $service->updatePlan(
            $case,
            $owner,
            'Nhập bù từ Kho Tổng, kiểm kê lại và đối chiếu giao dịch bán trong ngày.',
            $owner->id,
            now()->addDay()->toDateString(),
            'Đang điều tra chênh lệch nhập bù và xác nhận số lượng thực tế.',
            'receiving_shortage',
        );

        $this->assertSame('in_progress', $case->fresh()->status);

        $inventory->update([
            'quantity_on_hand' => 4,
            'theoretical_quantity' => 4,
        ]);

        $correction = InventoryTransaction::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'inventory_id' => $inventory->id,
            'performed_by' => $owner->id,
            'type' => 'purchase',
            'direction' => 'in',
            'quantity' => 7,
            'unit_cost' => 100,
            'total_cost' => 700,
            'quantity_before' => -3,
            'quantity_after' => 4,
            'occurred_at' => now(),
        ]);

        $this->assertSame('in_progress', $case->fresh()->status);
        app(NegativeInventoryService::class)->submitVerification(
            $case->fresh(),
            $owner,
            'Đã nhập bù, đối chiếu giao dịch và gửi xác minh độc lập.',
        );

        $this->assertSame('pending_verification', $case->fresh()->status);
        app(NegativeInventoryService::class)->verifyAndResolve(
            $case->fresh(),
            $owner,
            'restocked',
            'Đã xác minh giao dịch nhập bù và tồn thực tế về dương.',
        );

        $this->assertSame('resolved', $case->fresh()->status);
        $this->assertSame('restocked', $case->fresh()->resolution_type);
        $this->assertSame($correction->id, $case->fresh()->verification_transaction_id);
    }

    public function test_negative_case_cannot_be_closed_while_balance_is_still_negative(): void
    {
        [$owner, $restaurant, $branch, $ingredient] = $this->inventoryFixture();
        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => -1,
            'theoretical_quantity' => -1,
            'last_cost' => 100,
        ]);

        $case = InventoryNegativeCase::withoutGlobalScopes()
            ->where('inventory_id', $inventory->id)
            ->firstOrFail();

        $this->expectException(ValidationException::class);
        app(NegativeInventoryService::class)->resolve(
            $case,
            $owner,
            'verified',
            'Đã kiểm tra nhưng tồn vẫn đang âm, chưa thể chốt.',
        );
    }

    public function test_pos_sale_keeps_real_shortage_as_negative_inventory(): void
    {
        [$owner, $restaurant, $branch, $ingredient] = $this->inventoryFixture();
        $category = ProductCategory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Món âm tồn',
            'slug' => 'mon-am-ton',
            'status' => 'active',
        ]);
        $product = Product::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'code' => 'NEG-001',
            'name' => 'Món bán khi thiếu tồn',
            'slug' => 'mon-ban-khi-thieu-ton',
            'description' => 'Món kiểm thử âm nguyên liệu.',
            'price' => 50000,
            'is_active' => true,
            'is_available' => true,
            'track_inventory' => true,
        ]);
        ProductRecipe::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $ingredient->unit_id,
            'quantity' => 2,
            'waste_rate' => 0,
        ]);
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'created_by' => $owner->id,
            'order_number' => 'NEG-ORDER-001',
            'channel' => 'dine_in',
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal' => 50000,
            'total_amount' => 50000,
        ]);
        OrderItem::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
            'status' => 'pending',
        ]);
        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 0,
            'theoretical_quantity' => 0,
            'last_cost' => 100,
        ]);

        app(InventoryService::class)->deductInventoryForOrder($order, $owner);

        $this->assertSame(-2.0, (float) $inventory->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_transactions', [
            'order_id' => $order->id,
            'type' => 'usage',
            'quantity' => 2,
            'quantity_after' => -2,
        ]);
        $this->assertDatabaseHas('inventory_negative_cases', [
            'inventory_id' => $inventory->id,
            'negative_quantity' => 2,
            'status' => 'open',
        ]);
    }

    public function test_high_risk_case_requires_owner_approval_before_closure(): void
    {
        [$owner, $restaurant, $branch, $ingredient] = $this->inventoryFixture();
        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $manager->assignRole('manager');

        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => -10,
            'theoretical_quantity' => -10,
            'last_cost' => 150000,
        ]);
        $case = InventoryNegativeCase::withoutGlobalScopes()
            ->where('inventory_id', $inventory->id)
            ->firstOrFail();

        $service = app(NegativeInventoryService::class);
        $service->updatePlan(
            $case,
            $manager,
            'Đối chiếu đơn bán, lập phiếu nhập bù và kiểm kê lại khu vực lưu trữ.',
            $manager->id,
            now()->addDay()->toDateString(),
            'Bán vượt tồn khả dụng, cần xác minh lại thời điểm cấp phát.',
        );

        $this->assertSame('high', $case->fresh()->severity);
        $this->assertSame('pending_owner_approval', $case->fresh()->status);

        $service->decideApproval(
            $case->fresh(),
            $owner,
            'approve',
            'Đã kiểm tra phương án và cho phép xử lý theo kế hoạch.',
        );
        $this->assertSame('in_progress', $case->fresh()->status);

        $inventory->update(['quantity_on_hand' => 1, 'theoretical_quantity' => 1]);

        $this->expectException(ValidationException::class);
        $service->resolve(
            $case->fresh(),
            $manager,
            'restocked',
            'Đã bù tồn và đối chiếu số liệu thực tế.',
        );
    }

    public function test_standard_workflow_requires_ledger_correction_and_independent_verification(): void
    {
        [$owner, $restaurant, $branch, $ingredient] = $this->inventoryFixture();
        $responsible = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $responsible->assignRole('manager');
        $verifier = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $verifier->assignRole('manager');

        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => -1,
            'theoretical_quantity' => -1,
            'last_cost' => 100,
        ]);
        $case = InventoryNegativeCase::withoutGlobalScopes()->where('inventory_id', $inventory->id)->firstOrFail();

        $service = app(NegativeInventoryService::class);
        $service->updatePlan(
            $case,
            $responsible,
            'Kiểm tra giao dịch xuất, nhập bù và kiểm kê lại khu vực lưu trữ.',
            $responsible->id,
            now()->addDay()->toDateString(),
            'Đã xuất dùng trước khi phiếu nhập được ghi nhận.',
            'sales_before_receipt',
            'Tạm dừng cấp phát nguyên liệu này cho đến khi đối chiếu xong.',
            'Thiết lập kiểm soát không cho hoàn tất giao dịch nếu thiếu tồn.',
        );

        $this->assertSame('in_progress', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->case_code);
        $this->assertSame(72, $case->fresh()->sla_hours);

        $inventory->update([
            'quantity_on_hand' => 2,
            'theoretical_quantity' => 2,
        ]);
        $correction = InventoryTransaction::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'inventory_id' => $inventory->id,
            'performed_by' => $responsible->id,
            'type' => 'purchase',
            'direction' => 'in',
            'quantity' => 3,
            'unit_cost' => 100,
            'total_cost' => 300,
            'quantity_before' => -1,
            'quantity_after' => 2,
            'occurred_at' => now(),
            'notes' => 'Phiếu nhập bù cho hồ sơ âm.',
        ]);

        $service->submitVerification($case->fresh(), $responsible, 'Đã kiểm tra giao dịch nhập bù và gửi người độc lập đối chiếu.');
        $this->assertDatabaseHas('inventory_negative_cases', [
            'id' => $case->id,
            'status' => 'pending_verification',
            'verification_transaction_id' => $correction->id,
        ]);

        $service->verifyAndResolve(
            $case->fresh(),
            $verifier,
            'verified',
            'Đã đối chiếu phiếu nhập bù, tồn thực tế và sổ kho; số dư khớp.',
        );

        $this->assertDatabaseHas('inventory_negative_cases', [
            'id' => $case->id,
            'status' => 'resolved',
            'verified_by' => $verifier->id,
            'verification_status' => 'verified',
        ]);
        $this->assertGreaterThanOrEqual(4, InventoryNegativeCaseEvent::where('negative_case_id', $case->id)->count());
    }

    /** @return array{0: User, 1: Restaurant, 2: RestaurantBranch, 3: Ingredient} */
    private function inventoryFixture(): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => $owner->id,
        ]);
        $owner->forceFill([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ])->save();
        $owner->assignRole('owner');

        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Nguyên liệu âm tồn test',
            'sku' => 'NEGATIVE-STOCK-001',
            'average_cost' => 100,
            'status' => 'active',
        ]);

        return [$owner, $restaurant, $branch, $ingredient];
    }
}
