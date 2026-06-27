<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->normalUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_guests_and_normal_users_cannot_access_settings_panel(): void
    {
        $response = $this->get('/super-admin/settings');
        $response->assertRedirect(route('login'));

        $this->actingAs($this->normalUser);
        $response = $this->get('/super-admin/settings');
        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_settings_panel(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->get('/super-admin/settings');
        $response->assertOk();
        $response->assertSee('settings');
    }

    public function test_super_admin_can_update_settings(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->post('/super-admin/settings', [
            'chatbot_similarity_threshold' => 0.35,
            'chatbot_max_suggestions'      => 8,
            'chatbot_cache_ttl'            => 600,
            'mail_driver'                  => 'smtp',
            'mail_smtp_host'               => 'smtp.mailtrap.io',
            'mail_smtp_port'               => 2525,
            'mail_smtp_username'           => 'user_test',
            'mail_smtp_password'           => 'pass_test',
            'mail_smtp_encryption'         => 'tls',
            'mail_from_address'            => 'sender@test.com',
            'mail_from_name'               => 'SaaS Test',
            'upload_menu_image_max'        => 1024,
            'upload_invoice_image_max'     => 2048,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertEquals(0.35, (float) SystemSetting::get('chatbot_similarity_threshold'));
        $this->assertEquals(8, (int) SystemSetting::get('chatbot_max_suggestions'));
        $this->assertEquals(600, (int) SystemSetting::get('chatbot_cache_ttl'));
        $this->assertEquals('smtp.mailtrap.io', SystemSetting::get('mail_smtp_host'));
        $this->assertEquals(2525, (int) SystemSetting::get('mail_smtp_port'));
        $this->assertEquals(1024, (int) SystemSetting::get('upload_menu_image_max'));
        $this->assertEquals(2048, (int) SystemSetting::get('upload_invoice_image_max'));
    }

    public function test_settings_caching_and_invalidation(): void
    {
        SystemSetting::set('chatbot_similarity_threshold', '0.28');
        $this->assertEquals('0.28', SystemSetting::get('chatbot_similarity_threshold'));

        $cachedValue = Cache::get('system_setting:chatbot_similarity_threshold');
        $this->assertEquals('0.28', $cachedValue);

        DB::table('system_settings')
            ->where('key', 'chatbot_similarity_threshold')
            ->update(['value' => '0.50']);

        $this->assertEquals('0.28', SystemSetting::get('chatbot_similarity_threshold'));

        SystemSetting::set('chatbot_similarity_threshold', '0.42');
        $this->assertEquals('0.42', SystemSetting::get('chatbot_similarity_threshold'));
    }

    public function test_dynamic_mail_config_overrides(): void
    {
        SystemSetting::set('mail_driver', 'smtp');
        SystemSetting::set('mail_smtp_host', 'dynamic-smtp.domain.local');
        SystemSetting::set('mail_smtp_port', '465');
        SystemSetting::set('mail_smtp_username', 'user_dynamic');
        SystemSetting::set('mail_smtp_password', 'pass_dynamic');
        SystemSetting::set('mail_smtp_encryption', 'ssl');
        SystemSetting::set('mail_from_address', 'dynamic@sender.com');
        SystemSetting::set('mail_from_name', 'Dynamic System Name');

        config(['mail.default' => 'old_driver']);

        $provider = new \App\Providers\AppServiceProvider(app());
        $reflector = new \ReflectionClass(\App\Providers\AppServiceProvider::class);
        $method = $reflector->getMethod('loadDynamicSettings');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertEquals('smtp', config('mail.default'));
        $this->assertEquals('dynamic-smtp.domain.local', config('mail.mailers.smtp.host'));
        $this->assertEquals(465, config('mail.mailers.smtp.port'));
        $this->assertEquals('user_dynamic', config('mail.mailers.smtp.username'));
        $this->assertEquals('pass_dynamic', config('mail.mailers.smtp.password'));
        $this->assertEquals('ssl', config('mail.mailers.smtp.encryption'));
        $this->assertEquals('dynamic@sender.com', config('mail.from.address'));
        $this->assertEquals('Dynamic System Name', config('mail.from.name'));
    }

    public function test_sensitive_values_are_encrypted_in_database(): void
    {
        SystemSetting::set('mail_smtp_password', 'my_secret_pass');

        $rawValue = DB::table('system_settings')
            ->where('key', 'mail_smtp_password')
            ->value('value');

        $this->assertNotEquals('my_secret_pass', $rawValue);
        $this->assertEquals('my_secret_pass', Crypt::decryptString($rawValue));
        $this->assertEquals('my_secret_pass', SystemSetting::get('mail_smtp_password'));
    }

    public function test_sensitive_keys_decrypt_fallback_for_legacy_plaintext(): void
    {
        DB::table('system_settings')->insert([
            'key' => 'mail_smtp_password',
            'value' => 'plain_legacy_pass',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('system_setting:mail_smtp_password');

        $this->assertEquals('plain_legacy_pass', SystemSetting::get('mail_smtp_password'));
    }

    public function test_passwords_are_not_sent_to_frontend(): void
    {
        $this->actingAs($this->superAdmin);

        SystemSetting::set('mail_smtp_password', 'super_secret');

        $response = $this->get('/super-admin/settings');
        $response->assertOk();

        $page = $response->original->getData()['page'];
        $settings = $page['props']['settings'];

        $this->assertEquals('', $settings['mail_smtp_password']);
        $this->assertTrue($settings['has_smtp_password']);
    }

    public function test_empty_password_does_not_overwrite_existing(): void
    {
        $this->actingAs($this->superAdmin);

        SystemSetting::set('mail_smtp_password', 'original_pass');

        $this->post('/super-admin/settings', [
            'chatbot_similarity_threshold' => 0.28,
            'chatbot_max_suggestions'      => 5,
            'chatbot_cache_ttl'            => 300,
            'mail_driver'                  => 'smtp',
            'mail_smtp_host'               => 'smtp.test.com',
            'mail_smtp_port'               => 587,
            'mail_smtp_username'           => 'user',
            'mail_smtp_password'           => '',
            'mail_smtp_encryption'         => 'tls',
            'mail_from_address'            => 'test@test.com',
            'mail_from_name'               => 'Test',
            'upload_menu_image_max'        => 2048,
            'upload_invoice_image_max'     => 4096,
        ]);

        $this->assertEquals('original_pass', SystemSetting::get('mail_smtp_password'));
    }

    public function test_test_email_rate_limited(): void
    {
        $this->actingAs($this->superAdmin);

        $this->post('/super-admin/settings/test-email', [
            'mail_driver' => 'smtp',
            'mail_smtp_host' => 'smtp.test.com',
            'mail_smtp_port' => 587,
        ]);

        $response = $this->post('/super-admin/settings/test-email', [
            'mail_driver' => 'smtp',
            'mail_smtp_host' => 'smtp.test.com',
            'mail_smtp_port' => 587,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
