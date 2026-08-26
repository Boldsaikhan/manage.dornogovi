<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_locks_for_biometric_when_user_has_webauthn(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-pass'),
        ]);

        $user->webauthnCredentials()->create([
            'credential_id' => 'abc123credentialidxxxx',
            'public_key' => 'pk',
            'sign_count' => 0,
            'device_name' => 'Test',
        ]);

        $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'secret-pass',
        ])->assertRedirect();

        $this->assertTrue(session(AppLock::SESSION_KEY));
        $this->assertSame(AppLock::MODE_BIOMETRIC, session(AppLock::MODE_KEY));
    }

    public function test_login_does_not_lock_without_webauthn(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-pass'),
        ]);

        $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'secret-pass',
        ])->assertRedirect();

        $this->assertFalse((bool) session(AppLock::SESSION_KEY));
    }

    public function test_app_lock_and_password_unlock(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-pass'),
        ]);

        $this->actingAs($user)
            ->postJson(route('app.lock'))
            ->assertOk()
            ->assertJson(['locked' => true]);

        $this->postJson(route('app.unlock.password'), ['password' => 'wrong'])
            ->assertStatus(422);

        $this->postJson(route('app.unlock.password'), ['password' => 'secret-pass'])
            ->assertOk()
            ->assertJson(['locked' => false]);
    }
}
