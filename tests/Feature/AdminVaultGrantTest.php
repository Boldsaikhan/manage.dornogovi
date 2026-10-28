<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use App\Models\UserCredential;
use App\Support\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Админ албан хаагч бүрийн нэвтрэх мэдээллийн санг тусад нь нээх, хаах.
 */
class AdminVaultGrantTest extends TestCase
{
    use RefreshDatabase;

    private function system(): System
    {
        return System::create([
            'slug' => 'shilen',
            'name' => 'Шилэн данс',
            'url' => 'https://shilen.gov.mn/home',
            'login_url' => 'https://shilen.gov.mn/login',
            'category' => 'Санхүү',
        ]);
    }

    public function test_admin_can_open_and_close_one_users_vault(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.vault.users.open', $user->id), ['hours' => 24])
            ->assertRedirect();

        $this->assertNotNull(Vault::grantedUntil($user->fresh()));

        $this->actingAs($admin)
            ->post(route('admin.vault.users.close', $user->id))
            ->assertRedirect();

        $this->assertNull(Vault::grantedUntil($user->fresh()));
    }

    public function test_a_granted_vault_lets_the_user_launch_without_unlocking(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $system->viewers()->syncWithoutDetaching([$user->id]);

        UserCredential::create([
            'user_id' => $user->id,
            'system_id' => $system->id,
            'username_encrypted' => 'bold@dornogovi.gov.mn',
            'password_encrypted' => 'sZ7!secret',
        ]);

        Vault::grant($user, 60);

        // Session-д юу ч нээгээгүй хэрнээ нэвтэрнэ.
        $this->actingAs($user->fresh())
            ->get(route('systems.launch', $system))
            ->assertOk()
            ->assertSee('bold@dornogovi.gov.mn');
    }

    public function test_the_user_can_still_close_a_vault_the_admin_opened(): void
    {
        $user = User::factory()->create();
        Vault::grant($user, 60);

        $this->actingAs($user->fresh())
            ->postJson(route('vault.lock'))
            ->assertOk()
            ->assertJson(['unlocked' => false]);

        $this->assertNull(Vault::grantedUntil($user->fresh()));
    }

    public function test_a_normal_user_cannot_open_someone_elses_vault(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.vault.users.open', $other->id), ['hours' => 24])
            ->assertForbidden();

        $this->assertNull(Vault::grantedUntil($other->fresh()));
    }
}
