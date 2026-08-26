<?php

namespace Tests\Feature;

use App\Models\LoginQrToken;
use App\Models\User;
use App\Support\Vault;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultQrUnlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_vault_stays_unlocked_for_four_hours(): void
    {
        $this->assertSame(240, Vault::MINUTES);

        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $this->actingAs($user)
            ->postJson(route('vault.unlock'), ['account_password' => 'secret'])
            ->assertOk()
            ->assertJsonPath('minutes', 240);

        $until = session(Vault::SESSION_KEY);
        $this->assertIsInt($until);
        $this->assertGreaterThan(now()->addMinutes(230)->timestamp, $until);
        $this->assertLessThanOrEqual(now()->addMinutes(241)->timestamp, $until);
    }

    public function test_phone_qr_unlocks_vault_for_same_user(): void
    {
        $this->withoutMiddleware(EncryptCookies::class);

        $user = User::factory()->create();

        $create = $this->actingAs($user)->postJson(route('vault.unlock.qr.create'));
        $create->assertOk();
        $token = $create->json('token');
        $clientSecret = $create->json('client_secret');
        $this->assertNotEmpty($clientSecret);

        // Утас зөвшөөрнө (өөр хүсэлт).
        $this->actingAs($user)
            ->post(route('login.qr.approve', $token))
            ->assertRedirect();

        $this->assertDatabaseHas('login_qr_tokens', [
            'token' => $token,
            'status' => LoginQrToken::APPROVED,
            'purpose' => LoginQrToken::PURPOSE_VAULT,
            'user_id' => $user->id,
        ]);

        // Компьютер status асууна (client_secret-тай).
        $this->actingAs($user)
            ->getJson(route('vault.unlock.qr.status', [
                'token' => $token,
                'client_secret' => $clientSecret,
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('unlocked', true);

        $this->assertDatabaseHas('login_qr_tokens', [
            'token' => $token,
            'status' => LoginQrToken::CONSUMED,
        ]);
    }

    public function test_other_user_cannot_approve_vault_qr(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $token = $this->actingAs($owner)
            ->postJson(route('vault.unlock.qr.create'))
            ->json('token');

        $this->actingAs($other)
            ->post(route('login.qr.approve', $token))
            ->assertSessionHasErrors('token');
    }
}
