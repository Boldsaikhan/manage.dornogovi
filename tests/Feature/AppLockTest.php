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

    public function test_mobile_login_does_not_lock_immediately(): void
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

        // Нууц үгээр нэвтэрсэн тул шууд биометрик дахин асуухгүй.
        $this->assertFalse((bool) session(AppLock::SESSION_KEY));
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

    public function test_mobile_app_lock_uses_password_mode(): void
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
                'mode' => AppLock::MODE_FULL,
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
            ->postJson(route('app.lock'), ['background' => true])
            ->assertOk()
            ->assertJson(['locked' => true]);

        $this->postJson(route('app.unlock.password'), ['password' => 'wrong'])
            ->assertStatus(422);

        $this->postJson(route('app.unlock.password'), ['password' => 'secret-pass'])
            ->assertOk()
            ->assertJson(['locked' => false]);
    }

    public function test_unlock_rejects_invalid_biometric_assertion(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-pass'),
        ]);
        $user->webauthnCredentials()->create([
            'credential_id' => 'cred-bad-assertion',
            'public_key' => 'pk',
            'sign_count' => 0,
            'device_name' => 'Phone',
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::MOBILE_UA)
            ->postJson(route('app.lock'), ['background' => true])
            ->assertOk();

        $this->postJson(route('app.unlock'), [
            'assertion' => ['id' => 'zzz', 'clientDataJSON' => 'zzz'],
        ])->assertStatus(422);

        // Түгжээ хэвээр — буруу биометрикээр нэвтэрч болохгүй.
        $this->assertTrue((bool) session(AppLock::SESSION_KEY));
    }

    public function test_password_only_route_ignores_biometric_assertion(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-pass'),
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::MOBILE_UA)
            ->postJson(route('app.lock'), ['background' => true])
            ->assertOk();

        // assertion явуулсан ч энэ маршрут нууц үг шаардана.
        $this->postJson(route('app.unlock.password'), [
            'assertion' => ['id' => 'zzz'],
        ])->assertStatus(422);

        $this->postJson(route('app.unlock.password'), ['password' => 'secret-pass'])
            ->assertOk()
            ->assertJson(['locked' => false]);
    }

    public function test_mobile_background_lock_persists_on_navigation(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-pass'),
        ]);
        $user->webauthnCredentials()->create([
            'credential_id' => 'cred-background',
            'public_key' => 'pk',
            'sign_count' => 0,
            'device_name' => 'Phone',
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::MOBILE_UA)
            ->postJson(route('app.lock'), ['background' => true])
            ->assertOk()
            ->assertJson(['locked' => true]);

        $this->assertTrue(session(AppLock::SESSION_KEY));
        $this->assertTrue((bool) session(AppLock::BACKGROUND_LOCK_KEY));

        $this->actingAs($user)
            ->withHeader('User-Agent', self::MOBILE_UA)
            ->get(route('dept.dashboard'))
            ->assertOk();

        $this->assertTrue((bool) session(AppLock::SESSION_KEY));
    }

    public function test_verify_options_use_discoverable_passkey(): void
    {
        $user = User::factory()->create();
        $user->webauthnCredentials()->create([
            'credential_id' => 'cred-verify-discoverable',
            'public_key' => 'pk',
            'sign_count' => 0,
            'device_name' => 'Phone',
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::MOBILE_UA)
            ->postJson(route('webauthn.verify.options'))
            ->assertOk()
            ->assertJsonPath('publicKey.allowCredentials', null);
    }

    public function test_unlock_does_not_require_biometric(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-pass'),
        ]);
        $user->webauthnCredentials()->create([
            'credential_id' => 'cred-no-bio-unlock',
            'public_key' => 'pk',
            'sign_count' => 0,
            'device_name' => 'Phone',
        ]);

        $this->actingAs($user)
            ->withHeader('User-Agent', self::MOBILE_UA)
            ->postJson(route('app.lock'), ['background' => true])
            ->assertOk();

        $this->postJson(route('app.unlock'), ['password' => 'secret-pass'])
            ->assertOk()
            ->assertJson(['locked' => false]);
    }
}
