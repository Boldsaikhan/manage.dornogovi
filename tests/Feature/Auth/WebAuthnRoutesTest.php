<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthnRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_request_login_options(): void
    {
        $this->postJson(route('webauthn.login.options'))
            ->assertOk()
            ->assertJsonStructure(['publicKey' => ['challenge', 'rpId', 'timeout']]);
    }

    public function test_authenticated_user_can_request_register_options(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('webauthn.register.options'))
            ->assertOk()
            ->assertJsonStructure(['publicKey' => ['challenge', 'rp', 'user', 'authenticatorSelection']]);
    }

    public function test_user_can_delete_own_credential(): void
    {
        $user = User::factory()->create();
        $credential = WebAuthnCredential::query()->create([
            'user_id' => $user->id,
            'credential_id' => 'test-cred-'.uniqid(),
            'public_key' => '-----BEGIN PUBLIC KEY-----\ntest\n-----END PUBLIC KEY-----',
            'sign_count' => 0,
            'device_name' => 'Test',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('webauthn.destroy', $credential))
            ->assertOk();

        $this->assertDatabaseMissing('webauthn_credentials', ['id' => $credential->id]);
    }

    public function test_user_cannot_delete_another_users_credential(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $credential = WebAuthnCredential::query()->create([
            'user_id' => $owner->id,
            'credential_id' => 'test-cred-'.uniqid(),
            'public_key' => '-----BEGIN PUBLIC KEY-----\ntest\n-----END PUBLIC KEY-----',
            'sign_count' => 0,
            'device_name' => 'Test',
        ]);

        $this->actingAs($other)
            ->deleteJson(route('webauthn.destroy', $credential))
            ->assertForbidden();
    }

    public function test_profile_does_not_include_webauthn_credentials(): void
    {
        $user = User::factory()->create();
        WebAuthnCredential::query()->create([
            'user_id' => $user->id,
            'credential_id' => 'test-cred-'.uniqid(),
            'public_key' => '-----BEGIN PUBLIC KEY-----\ntest\n-----END PUBLIC KEY-----',
            'sign_count' => 0,
            'device_name' => 'Утас',
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Edit')
                ->missing('webauthnCredentials')
            );
    }
}
