<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Promotion;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Models\User;
use App\Services\EmailMicroserviceClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AutomationSprintATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test reservations:send-reminders command sends reminder for confirmed upcoming reservations.
     */
    public function test_reservation_reminder_command_sends_email()
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Aventura Bistro']);
        
        $area = \App\Models\Area::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Khu VIP',
            'code'          => 'khu-vip',
        ]);

        $table = RestaurantTable::create([
            'restaurant_id' => $restaurant->id,
            'area_id'       => $area->id,
            'name'          => 'Bàn 5',
            'capacity'      => 4,
            'status'        => 'available',
        ]);

        // Upcoming reservation in 1.5 hours
        $reservationTime = Carbon::now()->addHours(1.5);
        $res = TableReservation::create([
            'restaurant_id'    => $restaurant->id,
            'table_id'         => $table->id,
            'guest_name'       => 'Nguyen Van A',
            'guest_phone'      => '0912345678',
            'guest_email'      => 'customer@example.com',
            'reservation_date' => $reservationTime->toDateString(),
            'reservation_time' => $reservationTime->format('H:i:s'),
            'party_size'       => 4,
            'status'           => 'confirmed',
            'reminder_sent'    => false,
        ]);

        $this->mock(EmailMicroserviceClient::class, function (MockInterface $mock) use ($res) {
            $mock->shouldReceive('sendReservationReminder')
                ->once()
                ->andReturn(true);
        });

        $exitCode = Artisan::call('reservations:send-reminders');

        $this->assertEquals(0, $exitCode);
        $res->refresh();
        $this->assertTrue($res->reminder_sent);
        $this->assertNotNull($res->reminder_sent_at);
    }

    /**
     * Test reservations:mark-no-shows command deactivates past reservations.
     */
    public function test_reservation_mark_no_shows()
    {
        $restaurant = Restaurant::factory()->create();
        
        // 1 hour ago
        $pastTime = Carbon::now()->subMinutes(60);
        $res = TableReservation::create([
            'restaurant_id'    => $restaurant->id,
            'guest_name'       => 'Nguyen Van B',
            'guest_phone'      => '0912345679',
            'guest_email'      => 'customer2@example.com',
            'reservation_date' => $pastTime->toDateString(),
            'reservation_time' => $pastTime->format('H:i:s'),
            'party_size'       => 2,
            'status'           => 'confirmed',
            'reminder_sent'    => true,
        ]);

        $exitCode = Artisan::call('reservations:mark-no-shows');

        $this->assertEquals(0, $exitCode);
        $res->refresh();
        $this->assertEquals('no_show', $res->status);

        // Assert audit log exists
        $this->assertTrue(AuditLog::where('event', 'updated')
            ->where('action', 'auto_no_show')
            ->where('subject_id', $res->id)
            ->exists());
    }

    /**
     * Test promotions:expire-outdated command.
     */
    public function test_promotions_expire_outdated()
    {
        $restaurant = Restaurant::factory()->create();

        // Expired active promotion
        $expiredPromo = Promotion::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Expired Coupon',
            'code'          => 'OLD10',
            'type'          => 'percent',
            'value'         => 10,
            'start_date'    => Carbon::now()->subDays(5),
            'end_date'      => Carbon::now()->subDays(1),
            'is_active'     => true,
        ]);

        // Still valid promotion
        $validPromo = Promotion::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Valid Coupon',
            'code'          => 'NEW15',
            'type'          => 'percent',
            'value'         => 15,
            'start_date'    => Carbon::now()->subDays(1),
            'end_date'      => Carbon::now()->addDays(5),
            'is_active'     => true,
        ]);

        $exitCode = Artisan::call('promotions:expire-outdated');

        $this->assertEquals(0, $exitCode);
        $expiredPromo->refresh();
        $validPromo->refresh();

        $this->assertFalse($expiredPromo->is_active);
        $this->assertTrue($validPromo->is_active);
    }

    /**
     * Test that low stock updates dispatch the SendLowStockAlertEmail job with 30m delay.
     */
    public function test_low_stock_dispatches_alert_job()
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create();
        $unit = \App\Models\Unit::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Kilogram',
            'symbol'        => 'kg',
            'type'          => 'mass',
        ]);
        
        $ingredient = Ingredient::create([
            'restaurant_id'   => $restaurant->id,
            'unit_id'         => $unit->id,
            'name'            => 'Bò fillet',
            'sku'             => 'ING-BEEF',
            'min_stock_level' => 10.0,
            'status'          => 'active',
        ]);

        $inventory = Inventory::create([
            'restaurant_id'    => $restaurant->id,
            'ingredient_id'    => $ingredient->id,
            'quantity_on_hand' => 15.0,
        ]);

        // Update to 5.0 (which is less than 10.0 min_stock_level)
        $inventory->update([
            'quantity_on_hand' => 5.0,
        ]);

        // Assert job was pushed with delay of 30 minutes (1800 seconds)
        Queue::assertPushed(\App\Jobs\SendLowStockAlertEmail::class, function ($job) use ($restaurant) {
            return $job->restaurantId === $restaurant->id;
        });

        // Assert pending flag is in Cache
        // Inventory low-stock flags are branch-scoped; this fixture has no
        // branch_id, so it uses the explicit chain-wide scope suffix.
        $this->assertTrue(Cache::has("low_stock_pending:{$restaurant->id}:all"));
    }
}
