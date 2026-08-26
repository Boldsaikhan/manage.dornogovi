<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppLockTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15';

    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0';

    public function test_mobile_login_locks_for_biometric_when_user_has_webauthn(): void
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

        $this->withHeader('User-Agent', self::MOBILE_UA)
            ->post(route('login'), [
                'login' => $user->email,
                'password' => 'secret-pass',
            ])->assertRedirect();

        $this->assertTrue(session(AppLock::SESSION_KEY));
        $this->assertSame(AppLock::MODE_BIOMETRIC, session(AppLock::MODE_KEY));
    }

    public function test_desktop_login_does_not_lock_even_with_webauthn(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-pass'),
        ]);

        $user->webauthnCredentials()->create([
            'credential_id' => 'desktop-no-lock',
            'public_key' => 'pk',
            'sign_count' => 0,
            'device_name' => 'PC',
        ]);

        $this->withHeader('User-Agent', self::DESKTOP_UA)
            ->post(route('login'), [
                'login' => $user->email,
                'password' => 'secret-pass',
            ])->assertRedirect();

        $this->assertFalse((bool) session(AppLock::SESSION_KEY));
    }

    public function test_login_does_not_lock_without_webauthn(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-pass'),
        ]);

        $this->withHeader('User-Agent', self::MOBILE_UA)
            ->post(route('login'), [
                'login' => $user->email,
                'password' => 'secret-pass',
            ])->assertRedirect();

        $this->assertFalse((bool) session(AppLock::SESSION_KEY));
    }

    public function test_mobile_app_lock_uses_biometric_mode(): void
    {
        $user = User::factory()->create();
        $user->webauthnCredentials()->create([
            'credential_id' => 'cred-biometric-mode',
            'public_key' => 'pk',
            'sign_count' => 0,
            'device_name' => 'Phone',
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::MOBILE_UA)
            ->postJson(route('app.lock'))
            ->assertOk()
            ->assertJson([
                'locked' => true,
                'mode' => AppLock::MODE_BIOMETRIC,
            ]);
    }

    public function test_desktop_app_lock_does_not_lock(): void
    {
        $user = User::factory()->create();
        $user->webauthnCredentials()->create([
            'credential_id' => 'cred-desktop',
            'public_key' => 'pk',
            'sign_count' => 0,
            'device_name' => 'PC',
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::DESKTOP_UA)
            ->postJson(route('app.lock'))
            ->assertOk()
            ->assertJson([
                'locked' => false,
                'mode' => null,
            ]);
    }

    public function test_desktop_clears_existing_lock_on_page_load(): void
    {
        $user = User::factory()->create();
        $user->webauthnCredentials()->create([
            'credential_id' => 'cred-clear',
            'public_key' => 'pk',
            'sign_count' => 0,
            'device_name' => 'Phone',
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::MOBILE_UA)
            ->postJson(route('app.lock'))
            ->assertOk();

        $this->assertTrue(session(AppLock::SESSION_KEY));

        $this->actingAs($user)
            ->withHeader('User-Agent', self::DESKTOP_UA)
            ->get(route('dept.dashboard'))
            ->assertOk();

        $this->assertFalse((bool) session(AppLock::SESSION_KEY));
    }

    public function test_mobile_user_agent_does_not_autolock_on_navigation(): void
    {
        $user = User::factory()->create();
        $user->webauthnCredentials()->create([
            'credential_id' => 'cred-mobile-nav',
            'public_key' => 'pk',
            'sign_count' => 0,
            'device_name' => 'Phone',
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::MOBILE_UA)
            ->get(route('dept.dashboard'))
            ->assertOk();

        // Цэс/хуудас шилжихэд автоматаар түгжихгүй
        $this->assertFalse((bool) session(AppLock::SESSION_KEY));
    }

    public function test_app_lock_and_password_unlock(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-pass'),
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::MOBILE_UA)
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
