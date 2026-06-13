<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\StorageQuotaWarningNotification;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantStorageAndQuotaManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $owner;
    protected Restaurant $restaurant;
    protected SubscriptionPlan $plan;
    protected RestaurantSubscription $subscription;
    protected QuotaService $quotaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quotaService = app(QuotaService::class);

        // 1. Setup SuperAdmin with 2FA confirmed
        $this->superAdmin = User::factory()->create([
            'two_factor_confirmed_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        // 2. Setup Owner
        $this->owner = User::factory()->create([
            'name' => 'John Owner',
            'email' => 'owner@pizza.com',
        ]);

        // 2. Setup Plan (500MB max storage)
        $this->plan = SubscriptionPlan::where('code', 'free')->first() ?? SubscriptionPlan::create([
            'name' => 'Free Plan',
            'code' => 'free',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'max_branches' => 1,
            'max_tables' => 10,
            'max_users' => 5,
            'status' => 'active',
        ]);

        $this->plan->update([
            'features' => array_merge((array)$this->plan->features, [
                'max_storage_mb' => 500,
            ])
        ]);

        // 4. Setup Restaurant
        $this->restaurant = Restaurant::create([
            'name' => 'Pizza Hut',
            'code' => 'PHUT1',
            'slug' => 'pizza-hut',
            'status' => 'active',
            'plan_id' => $this->plan->id,
            'owner_user_id' => $this->owner->id,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
        ]);

        // 5. Setup Subscription
        $this->subscription = RestaurantSubscription::create([
            'restaurant_id' => $this->restaurant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'started_at' => now()->subDays(1),
            'ended_at' => now()->addDays(29),
            'price' => 0,
        ]);
    }

    public function test_quota_service_returns_correct_default_limits_and_custom_overrides(): void
    {
        // Default Plan Limit is 500MB
        $this->assertEquals(500, $this->quotaService->getLimit($this->restaurant, 'storage_mb'));

        // Apply custom limit of 2000MB (2GB)
        $this->restaurant->update(['custom_storage_limit_mb' => 2000]);
        $this->restaurant->refresh();

        $this->assertEquals(2000, $this->quotaService->getLimit($this->restaurant, 'storage_mb'));
    }

    public function test_quota_service_calculates_correct_usage_in_mb(): void
    {
        // 0 MB initially
        $this->assertEquals(0, $this->quotaService->getUsage($this->restaurant)['storage_mb']);

        // Add a media asset of 10.5 MB (11,010,048 bytes)
        MediaAsset::create([
            'restaurant_id' => $this->restaurant->id,
            'file_name' => 'logo.jpg',
            'file_path' => 'logos/logo.jpg',
            'disk' => 'public',
            'size_bytes' => 11010048,
        ]);

        $this->assertEquals(10.5, $this->quotaService->getUsage($this->restaurant)['storage_mb']);
    }

    public function test_storage_exceeded_notification_triggers_and_throttles(): void
    {
        Notification::fake();

        // 90% of 500MB is 450MB. Let's create an asset of 451MB
        $bytes = 451 * 1024 * 1024;

        MediaAsset::create([
            'restaurant_id' => $this->restaurant->id,
            'file_name' => 'large_video.mp4',
            'file_path' => 'videos/large_video.mp4',
            'disk' => 'public',
            'size_bytes' => $bytes,
        ]);

        // Assert Notification Sent to Owner
        Notification::assertSentTo(
            $this->owner,
            StorageQuotaWarningNotification::class,
            function ($notification, $channels) {
                return $notification->percentage >= 90;
            }
        );

        $this->restaurant->refresh();
        $this->assertNotNull($this->restaurant->storage_warning_sent_at);
        $firstSentAt = $this->restaurant->storage_warning_sent_at;

        // Try to trigger warning again (should be throttled within 24h)
        Notification::fake();

        MediaAsset::create([
            'restaurant_id' => $this->restaurant->id,
            'file_name' => 'large_image.jpg',
            'file_path' => 'images/large_image.jpg',
            'disk' => 'public',
            'size_bytes' => 1 * 1024 * 1024,
        ]);

        Notification::assertNothingSent();
        $this->assertEquals($firstSentAt->timestamp, $this->restaurant->storage_warning_sent_at->timestamp);
    }

    public function test_storage_warning_timestamp_resets_when_usage_drops_below_90_percent(): void
    {
        $this->restaurant->update([
            'storage_warning_sent_at' => now()->subHours(2),
        ]);

        // Create asset exceeding 90%
        $asset = MediaAsset::create([
            'restaurant_id' => $this->restaurant->id,
            'file_name' => 'heavy.zip',
            'file_path' => 'heavy.zip',
            'disk' => 'public',
            'size_bytes' => 460 * 1024 * 1024,
        ]);

        $this->assertNotNull($this->restaurant->storage_warning_sent_at);

        // Delete the asset (dropping usage back to 0MB, which is < 90%)
        $asset->delete();

        $this->restaurant->refresh();
        $this->assertNull($this->restaurant->storage_warning_sent_at);
    }

    public function test_superadmin_can_update_custom_storage_quota(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->patch(route('superadmin.restaurants.storage-quota', $this->restaurant), [
                'custom_storage_limit_mb' => 1000,
            ]);

        $response->assertRedirect();
        $this->restaurant->refresh();
        $this->assertEquals(1000, $this->restaurant->custom_storage_limit_mb);
    }

    public function test_garbage_collector_lists_and_cleans_up_orphans(): void
    {
        Storage::fake('public');

        // Create physical file mock
        Storage::disk('public')->put('orphans/dummy.png', 'file content');

        // 1. Create an orphan asset (no attachable link)
        $orphan = MediaAsset::create([
            'restaurant_id' => $this->restaurant->id,
            'file_name' => 'dummy.png',
            'file_path' => 'orphans/dummy.png',
            'disk' => 'public',
            'size_bytes' => 1000,
        ]);

        // 2. Create a non-orphan asset linked to a Product
        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Pizza Margherita',
            'code' => 'PIZZA-MARG',
            'slug' => 'pizza-margherita',
            'price' => 120000,
            'is_active' => true,
        ]);

        $linkedAsset = MediaAsset::create([
            'restaurant_id' => $this->restaurant->id,
            'file_name' => 'pizza.jpg',
            'file_path' => 'products/pizza.jpg',
            'disk' => 'public',
            'size_bytes' => 2000,
            'attachable_type' => Product::class,
            'attachable_id' => $product->id,
        ]);

        // 3. Create a morph-broken orphan asset (Product doesn't exist)
        $brokenOrphan = MediaAsset::create([
            'restaurant_id' => $this->restaurant->id,
            'file_name' => 'broken.jpg',
            'file_path' => 'products/broken.jpg',
            'disk' => 'public',
            'size_bytes' => 3000,
            'attachable_type' => Product::class,
            'attachable_id' => 999999, // Non-existent ID
        ]);

        // Verify index page lists the 2 orphans
        $response = $this->actingAs($this->superAdmin)
            ->get(route('superadmin.garbage-collector.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('super-admin/GarbageCollector')
            ->has('orphans.data', 2)
            ->where('stats.total_count', 2)
        );

        // Perform cleanup on selected orphan
        Storage::disk('public')->assertExists('orphans/dummy.png');

        $responseClean = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.garbage-collector.cleanup'), [
                'ids' => [$orphan->id],
            ]);

        $responseClean->assertRedirect();
        Storage::disk('public')->assertMissing('orphans/dummy.png');
        $this->assertDatabaseMissing('media_assets', ['id' => $orphan->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $linkedAsset->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $brokenOrphan->id]);
    }
}
