<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantRevenueSummary;
use App\Models\User;
use App\Services\ForecastService;
use App\Services\FraudDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $this->owner = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);
    }

    public function test_ai_fraud_detection_falls_back_gracefully_when_python_is_offline(): void
    {
        // Ghi nhận một số log mẫu
        AuditLog::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->owner->id,
            'user_role' => 'owner',
            'action' => 'price_modified',
            'event' => 'updated',
            'subject_type' => Order::class,
            'subject_id' => 101,
            'old_values' => ['unit_price' => 50000],
            'new_values' => ['unit_price' => 80000],
            'ip_address' => '127.0.0.1',
        ]);

        // Giả lập Python service offline (Http client sẽ ném ngoại lệ hoặc trả lỗi)
        Http::fake([
            '*/api/analytics/fraud-detection' => Http::response(null, 500),
        ]);

        $service = new FraudDetectionService();

        $alerts = $service->detectAiFraudAlerts(
            $this->restaurant->id,
            now()->subDays(30)->toDateString(),
            now()->toDateString()
        );

        // 1. Phải kích hoạt Fallback và trả về các cảnh báo mô phỏng tiêu chuẩn
        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Laravel Fallback', $alerts[0]['description']);
    }

    public function test_ai_fraud_detection_returns_python_alerts_when_online(): void
    {
        Http::fake([
            '*/api/analytics/fraud-detection' => Http::response([
                'alerts' => [
                    [
                        'id' => 'ai-fraud-price-99',
                        'employee_name' => 'Nguyễn Văn Hùng',
                        'violation_type' => 'AI: Sửa giá món nhiều lần',
                        'severity' => 'high',
                        'description' => 'Sửa giá nhiều lần',
                        'penalty_amount' => 0.0,
                        'risk_score' => 97.5,
                        'reason' => 'Sửa liên tiếp trên đơn #99',
                    ]
                ]
            ], 200),
        ]);

        $service = new FraudDetectionService();

        $alerts = $service->detectAiFraudAlerts(
            $this->restaurant->id,
            now()->subDays(30)->toDateString(),
            now()->toDateString()
        );

        $this->assertCount(1, $alerts);
        $this->assertEquals('ai-fraud-price-99', $alerts[0]['id']);
        $this->assertStringContainsString('Python AI Service', $alerts[0]['description']);
    }

    public function test_ai_inventory_forecast_falls_back_gracefully_when_python_is_offline(): void
    {
        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Thịt Bò',
            'sku' => 'BEEF-TEST',
        ]);

        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 5000.0,
            'theoretical_quantity' => 5000.0,
        ]);

        Http::fake([
            '*/api/analytics/inventory-forecast' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson(route('inventory.ai-forecast'));

        $response->assertOk();
        $response->assertJsonStructure(['success', 'forecast']);
        
        $forecast = $response->json('forecast');
        $this->assertNotEmpty($forecast);
        $this->assertEquals($ingredient->id, $forecast[0]['ingredient_id']);
        $this->assertStringContainsString('Laravel Fallback', $forecast[0]['reason']);
    }

    public function test_ai_inventory_forecast_returns_python_prediction_when_online(): void
    {
        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Thịt Bò',
            'sku' => 'BEEF-TEST',
        ]);

        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 5000.0,
            'theoretical_quantity' => 5000.0,
        ]);

        Http::fake([
            '*/api/analytics/inventory-forecast' => Http::response([
                'success' => true,
                'forecast' => [
                    [
                        'ingredient_id' => $ingredient->id,
                        'ingredient_name' => 'Thịt Bò',
                        'sku' => 'BEEF-TEST',
                        'unit_symbol' => 'g',
                        'current_stock' => 5000.0,
                        'min_stock_level' => 1000.0,
                        'avg_daily_usage' => 150.0,
                        'predicted_usage_next_7_days' => 1100.0,
                        'suggested_purchase' => 200.0,
                        'confidence_score' => 95.5,
                        'reason' => 'Xu hướng tiêu thụ cao'
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson(route('inventory.ai-forecast'));

        $response->assertOk();
        $forecast = $response->json('forecast');
        
        $this->assertCount(1, $forecast);
        $this->assertEquals($ingredient->id, $forecast[0]['ingredient_id']);
        $this->assertStringContainsString('Python AI Service', $forecast[0]['reason']);
    }

    public function test_ai_revenue_forecast_falls_back_gracefully_when_python_is_offline(): void
    {
        // Thêm dữ liệu doanh thu quá khứ để PHP Fallback chạy được
        RestaurantRevenueSummary::create([
            'restaurant_id' => $this->restaurant->id,
            'scope_key' => 'restaurant',
            'summary_type' => 'daily',
            'summary_date' => today()->subDays(1)->toDateString(),
            'net_revenue' => 15000000,
            'order_count' => 100,
            'completed_order_count' => 100,
        ]);

        Http::fake([
            '*/api/analytics/revenue-forecast' => Http::response(null, 500),
        ]);

        $service = app(ForecastService::class);
        $forecast = $service->forecastTomorrow($this->restaurant->id);

        $this->assertNotNull($forecast);
        $this->assertStringContainsString('Laravel Fallback', $forecast['confidence_label']);
    }

    public function test_ai_revenue_forecast_returns_python_prediction_when_online(): void
    {
        Http::fake([
            '*/api/analytics/revenue-forecast' => Http::response([
                'tomorrow' => [
                    'amount' => 18500000,
                    'confidence' => 'high',
                    'confidence_label' => 'Cao (Hồi quy AI)',
                    'trend_factor' => 1.15
                ],
                'next_7_days' => [
                    ['date' => '01/06', 'revenue' => 18000000, 'is_forecast' => true]
                ]
            ], 200),
        ]);

        $service = app(ForecastService::class);
        $forecast = $service->forecastTomorrow($this->restaurant->id);

        $this->assertEquals(18500000, $forecast['amount']);
        $this->assertStringContainsString('Hồi quy AI', $forecast['confidence_label']);

        $chart = $service->forecast7Days($this->restaurant->id);
        $this->assertCount(1, $chart);
        $this->assertEquals(18000000, $chart[0]['revenue']);
    }
}
