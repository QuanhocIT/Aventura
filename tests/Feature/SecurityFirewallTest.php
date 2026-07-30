<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityFirewallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear caches and rate limits before each test
        Cache::flush();
        RateLimiter::clear('rate_limit:ip:127.0.0.1');
        RateLimiter::clear('rate_limit:ip:127.0.0.2');
        RateLimiter::clear('rate_limit:register:ip:127.0.0.1');
        RateLimiter::clear('rate_limit:forgot_password:ip:127.0.0.1');
        
        // Remove whitelists and custom settings
        SystemSetting::whereIn('key', [
            'firewall_whitelist',
            'waf_login_max_attempts',
            'waf_login_decay_seconds',
            'waf_login_block_minutes',
            'rate_limit_global_max',
            'rate_limit_global_decay'
        ])->delete();
    }

    public function test_global_rate_limiter_allows_requests_under_limit(): void
    {
        config(['firewall.rate_limit.global.max_attempts' => 3]);
        config(['firewall.rate_limit.global.decay_seconds' => 60]);

        // Request 1, 2, 3 should succeed
        for ($i = 0; $i < 3; $i++) {
            $response = $this->get('/api/docs');
            $response->assertStatus(200);
            $response->assertHeader('X-RateLimit-Limit', 3);
            $response->assertHeader('X-RateLimit-Remaining');
        }
    }

    public function test_global_rate_limiter_blocks_requests_over_limit(): void
    {
        config(['firewall.rate_limit.global.max_attempts' => 2]);
        config(['firewall.rate_limit.global.decay_seconds' => 60]);

        // Request 1
        $this->get('/api/docs')->assertStatus(200);
        // Request 2
        $this->get('/api/docs')->assertStatus(200);
        
        // Request 3 should be blocked
        $response = $this->get('/api/docs');
        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    public function test_excluded_paths_bypass_rate_limiting(): void
    {
        config(['firewall.rate_limit.global.max_attempts' => 1]);
        config(['firewall.rate_limit.global.decay_seconds' => 60]);

        // Excluded path (like /api/health)
        $this->get('/api/health')->assertStatus(200);
        $this->get('/api/health')->assertStatus(200); // Should still succeed
    }

    public function test_waf_blocks_ip_after_too_many_failed_logins(): void
    {
        config(['firewall.waf.login.max_attempts' => 3]);
        config(['firewall.waf.login.decay_seconds' => 10]);
        config(['firewall.waf.login.block_minutes' => 30]);

        // Trigger failed login events directly
        for ($i = 0; $i < 3; $i++) {
            event(new \Illuminate\Auth\Events\Failed('web', null, ['email' => 'invalid@example.com', 'password' => 'wrong']));
        }

        // Verify the IP is blocked
        $this->assertTrue(Cache::has('waf:blocked:127.0.0.1'));

        // Subsequent requests should return 403 Forbidden
        $response = $this->get('/api/docs');
        $response->assertStatus(403);
    }

    public function test_whitelist_bypasses_rate_limiting_and_waf_blocks(): void
    {
        // 1. Whitelist the IP
        SystemSetting::set('firewall_whitelist', json_encode(['127.0.0.1']));

        // 2. Set strict rate limits
        config(['firewall.rate_limit.global.max_attempts' => 1]);
        config(['firewall.rate_limit.global.decay_seconds' => 60]);

        // Multiple requests should succeed because IP is whitelisted
        $this->get('/api/docs')->assertStatus(200);
        $this->get('/api/docs')->assertStatus(200);

        // 3. Trigger failed logins that would normally block the IP
        config(['firewall.waf.login.max_attempts' => 2]);
        for ($i = 0; $i < 3; $i++) {
            event(new \Illuminate\Auth\Events\Failed('web', null, ['email' => 'invalid@example.com', 'password' => 'wrong']));
        }

        // Should NOT block the IP and requests should still pass
        $this->get('/api/docs')->assertStatus(200);
    }

    public function test_route_specific_rate_limiting_for_registration(): void
    {
        // Global is 60, but register override is 5
        // Send 5 post requests to /register, 6th should fail with 429
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/register', [
                'name' => 'Test User',
                'email' => "user{$i}@example.com",
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);
            // It might fail validation (missing inputs/already exists) but should NOT be 429
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 6th request
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'user6@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $response->assertStatus(429);
    }

    public function test_super_admin_firewall_management(): void
    {
        // 1. Create a Super Admin User
        $superAdmin = User::factory()->create([
            'two_factor_confirmed_at' => now(),
        ]);
        $superAdmin->assignRole('super_admin');

        // 2. Mock a blocked IP
        Cache::put("waf:blocked:1.2.3.4", true, 600);
        Cache::put("waf:blocked_list", ["1.2.3.4" => time() + 600], 600);

        // 3. Request index page as Super Admin
        $response = $this->actingAs($superAdmin)
            ->get('/super-admin/firewall');
        $response->assertStatus(200);

        // 4. Test unblocking IP via Super Admin Controller
        $response = $this->actingAs($superAdmin)
            ->delete('/super-admin/firewall/blocked/1.2.3.4');
        $response->assertRedirect();
        $this->assertFalse(Cache::has('waf:blocked:1.2.3.4'));

        // 5. Test adding IP to Whitelist
        $response = $this->actingAs($superAdmin)
            ->post('/super-admin/firewall/whitelist', ['ip' => '5.6.7.8']);
        $response->assertRedirect();
        $whitelist = json_decode(SystemSetting::get('firewall_whitelist', '[]'), true);
        $this->assertContains('5.6.7.8', $whitelist);

        // 6. Test removing IP from Whitelist
        $response = $this->actingAs($superAdmin)
            ->delete('/super-admin/firewall/whitelist/5.6.7.8');
        $response->assertRedirect();
        $whitelist = json_decode(SystemSetting::get('firewall_whitelist', '[]'), true);
        $this->assertNotContains('5.6.7.8', $whitelist);

        // 7. Test updating Firewall settings
        $response = $this->actingAs($superAdmin)
            ->post('/super-admin/firewall/settings', [
                'waf_login_max_attempts' => 15,
                'waf_login_decay_seconds' => 30,
                'waf_login_block_minutes' => 60,
                'rate_limit_global_max' => 120,
                'rate_limit_global_decay' => 60,
            ]);
        $response->assertRedirect();
        
        $this->assertEquals(15, SystemSetting::get('waf_login_max_attempts'));
        $this->assertEquals(120, SystemSetting::get('rate_limit_global_max'));
    }
}
