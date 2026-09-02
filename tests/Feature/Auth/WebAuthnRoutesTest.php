<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthnRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_login_options_require_phone(): void
    {
        $this->postJson(route('webauthn.login.options'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Утасны дугаараа оруулна уу.');
    }

    public function test_login_options_use_discoverable_when_phone_matches(): void
    {
        $user = User::factory()->create(['phone' => '89112233']);
        WebAuthnCredential::query()->create([
            'user_id' => $user->id,
            'credential_id' => 'test-cred-phone',
            'public_key' => '-----BEGIN PUBLIC KEY-----\ntest\n-----END PUBLIC KEY-----',
            'sign_count' => 0,
            'device_name' => 'Утас',
        ]);

        $this->postJson(route('webauthn.login.options'), ['login' => '8911 2233'])
            ->assertOk()
            ->assertJsonPath('publicKey.allowCredentials', null);
    }

    public function test_login_options_rejects_user_without_webauthn(): void
    {
        $user = User::factory()->create(['phone' => '89112233']);

        $this->postJson(route('webauthn.login.options'), ['login' => $user->phone])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Энэ утсанд хуруу/царай бүртгэгдээгүй. Эхлээд нууц үгээр нэвтэрч, «Идэвхжүүлэх» дарна уу.']);
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

    public function test_profile_includes_webauthn_credentials(): void
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
                ->has('webauthnCredentials', 1)
                ->where('webauthnCredentials.0.device_name', 'Утас')
            );
    }
}
