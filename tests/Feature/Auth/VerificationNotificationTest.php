<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_sends_verification_notification(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->unverified()->create();
        $user->assignRole('super_admin');

        $mock = $this->mock(EmailVerificationService::class);
        $mock->shouldReceive('send')
            ->once()
            ->with(\Mockery::on(fn ($u) => $u->id === $user->id))
            ->andReturn(true);

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('home'));
    }

    public function test_does_not_send_verification_notification_if_email_is_verified(): void
    {
        $user = User::factory()->create();

        $mock = $this->mock(EmailVerificationService::class);
        $mock->shouldNotReceive('send');

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
