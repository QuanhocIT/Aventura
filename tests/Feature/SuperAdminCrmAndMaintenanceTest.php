<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantFollowup;
use App\Models\RestaurantInternalNote;
use App\Models\RestaurantTag;
use App\Models\SystemMaintenanceSchedule;
use App\Models\User;
use App\Services\EmailMicroserviceClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SuperAdminCrmAndMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user and mock role check with two-factor confirmed
        $this->admin = User::factory()->create([
            'two_factor_confirmed_at' => now(),
        ]);
        $this->admin->assignRole('super_admin');

        $this->restaurant = Restaurant::factory()->create();
    }

    /**
     * Test admin can create and delete crm notes.
     */
    public function test_admin_can_add_and_delete_crm_notes()
    {
        $this->actingAs($this->admin);

        // Store note
        $response = $this->post(route('superadmin.restaurants.notes.store', $this->restaurant->id), [
            'note' => 'Test internal note here',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('restaurant_internal_notes', [
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'note' => 'Test internal note here',
        ]);

        $note = RestaurantInternalNote::first();

        // Delete note
        $delResponse = $this->delete(route('superadmin.restaurants.notes.destroy', [$this->restaurant->id, $note->id]));
        $delResponse->assertRedirect();
        $this->assertDatabaseMissing('restaurant_internal_notes', ['id' => $note->id]);
    }

    /**
     * Test admin can manage crm tags.
     */
    public function test_admin_can_add_and_delete_crm_tags()
    {
        $this->actingAs($this->admin);

        // Store tag
        $response = $this->post(route('superadmin.restaurants.tags.store', $this->restaurant->id), [
            'name' => 'VIP',
            'color' => 'amber',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('restaurant_tags', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'VIP',
            'color' => 'amber',
        ]);

        $tag = RestaurantTag::first();

        // Delete tag
        $delResponse = $this->delete(route('superadmin.restaurants.tags.destroy', [$this->restaurant->id, $tag->id]));
        $delResponse->assertRedirect();
        $this->assertDatabaseMissing('restaurant_tags', ['id' => $tag->id]);
    }

    /**
     * Test admin can manage followups.
     */
    public function test_admin_can_add_and_complete_followups()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('superadmin.restaurants.followups.store', $this->restaurant->id), [
            'note' => 'Call client tomorrow',
            'remind_at' => Carbon::now()->addDays(2)->toDateTimeString(),
            'assigned_to' => $this->admin->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('restaurant_followups', [
            'restaurant_id' => $this->restaurant->id,
            'note' => 'Call client tomorrow',
            'status' => 'pending',
        ]);

        $followup = RestaurantFollowup::first();

        // Complete followup
        $compResponse = $this->patch(route('superadmin.restaurants.followups.complete', [$this->restaurant->id, $followup->id]));
        $compResponse->assertRedirect();
        $this->assertDatabaseHas('restaurant_followups', [
            'id' => $followup->id,
            'status' => 'completed',
        ]);
    }

    /**
     * Test maintenance scheduler artisan command.
     */
    public function test_artisan_command_activates_and_completes_maintenance_schedules()
    {
        // 1. Create upcoming maintenance schedule starting now
        $schedule = SystemMaintenanceSchedule::create([
            'title' => 'Nâng cấp Database',
            'downtime_start' => Carbon::now()->subMinutes(5),
            'downtime_end' => Carbon::now()->addHours(2),
            'services' => ['mysql'],
            'status' => 'scheduled',
        ]);

        // Run command
        Artisan::call('system:check-maintenance');

        // Assert it is activated
        $this->assertDatabaseHas('system_maintenance_schedules', [
            'id' => $schedule->id,
            'status' => 'active',
        ]);

        // 2. Test ending maintenance
        $schedule->update([
            'downtime_end' => Carbon::now()->subMinutes(1),
        ]);

        // Run command again
        Artisan::call('system:check-maintenance');

        // Assert it is completed
        $this->assertDatabaseHas('system_maintenance_schedules', [
            'id' => $schedule->id,
            'status' => 'completed',
        ]);
    }

    /**
     * Test artisan command sends warning emails.
     */
    public function test_artisan_command_triggers_email_notifications()
    {
        // Create an owner user
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $schedule = SystemMaintenanceSchedule::create([
            'title' => 'Bảo trì khẩn cấp',
            'downtime_start' => Carbon::now()->addMinutes(45),
            'downtime_end' => Carbon::now()->addHours(2),
            'services' => ['mysql'],
            'status' => 'scheduled',
            'notified_24h' => true,
            'notified_1h' => false,
        ]);

        // Mock EmailMicroserviceClient
        $this->mock(EmailMicroserviceClient::class, function (MockInterface $mock) use ($owner) {
            $mock->shouldReceive('sendCampaignEmail')
                ->once()
                ->with(Mockery::on(function ($data) use ($owner) {
                    return $data['email'] === $owner->email
                        && str_contains($data['title'], 'bảo trì hệ thống Aventura');
                }))
                ->andReturn(true);
        });

        // Run command
        Artisan::call('system:check-maintenance');

        // Assert notified flag updated to true
        $this->assertDatabaseHas('system_maintenance_schedules', [
            'id' => $schedule->id,
            'notified_1h' => true,
        ]);
    }
}
