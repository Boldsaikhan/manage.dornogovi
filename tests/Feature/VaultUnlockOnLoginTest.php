<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use App\Models\UserCredential;
use App\Support\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultUnlockOnLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_vault_is_open_right_after_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('account-pw')]);

        $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'account-pw',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(session(Vault::SESSION_KEY));
    }

    public function test_saved_system_launches_without_asking_again(): void
    {
        $user = User::factory()->create(['password' => bcrypt('account-pw')]);

        $system = System::create([
            'slug' => 'erp',
            'name' => 'Төрийн ERP',
            'url' => 'https://erp.example.mn',
            'login_method' => System::LOGIN_MANUAL,
            'requires_login' => true,
            'is_active' => true,
            'is_internal' => false,
            'sort_order' => 1,
        ]);
        $system->viewers()->attach($user->id);

        UserCredential::create([
            'user_id' => $user->id,
            'system_id' => $system->id,
            'username_encrypted' => 'hereglegch',
            'password_encrypted' => 'nuuts',
        ]);

        $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'account-pw',
        ])->assertRedirect();

        // Сан нээлттэй тул шууд нэвтрэх хуудас гарна.
        $this->get(route('systems.launch', $system->id))
            ->assertOk()
            ->assertSee('hereglegch', false);
    }

    public function test_manual_lock_still_works(): void
    {
        $user = User::factory()->create(['password' => bcrypt('account-pw')]);

        $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'account-pw',
        ]);

        $this->assertNotNull(session(Vault::SESSION_KEY));

        $this->postJson(route('vault.lock'))->assertOk();

        $this->assertNull(session(Vault::SESSION_KEY));
    }
}
