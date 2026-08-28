<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use App\Models\UserCredential;
use App\Support\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchTest extends TestCase
{
    use RefreshDatabase;

    private function system(array $attributes = []): System
    {
        return System::create(array_merge([
            'slug' => 'shilen',
            'name' => 'Шилэн данс',
            'url' => 'https://shilen.gov.mn/home',
            'login_url' => 'https://shilen.gov.mn/login',
            'category' => 'Санхүү',
        ], $attributes));
    }

    private function withCredential(System $system, User $user): UserCredential
    {
        $system->viewers()->syncWithoutDetaching([$user->id]);

        return UserCredential::create([
            'user_id' => $user->id,
            'system_id' => $system->id,
            'username_encrypted' => 'bold@dornogovi.gov.mn',
            'password_encrypted' => 'sZ7!secret',
        ]);
    }

    public function test_vault_starts_locked_and_blocks_launching(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $this->withCredential($system, $user);

        $this->actingAs($user)
            ->get(route('systems.launch', $system))
            ->assertStatus(423);
    }

    public function test_unlocking_requires_the_correct_account_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('account-pw')]);

        $this->actingAs($user)
            ->postJson(route('vault.unlock'), ['account_password' => 'wrong'])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('vault.unlock'), ['account_password' => 'account-pw'])
            ->assertOk()
            ->assertJson(['unlocked' => true]);
    }

    public function test_launch_serves_credentials_once_the_vault_is_unlocked(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $this->withCredential($system, $user);

        $this->actingAs($user);
        $this->withSession([Vault::SESSION_KEY => now()->addHour()->timestamp]);

        $response = $this->get(route('systems.launch', $system))
            ->assertOk()
            ->assertSee('bold@dornogovi.gov.mn')
            ->assertSee('sZ7!secret');

        // Нэвтрэх мэдээлэл агуулсан хуудсыг кэшлэхийг хориглосон эсэх.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_launch_auto_submits_when_the_system_is_configured(): void
    {
        $user = User::factory()->create();
        $system = $this->system([
            'login_method' => System::LOGIN_FORM_POST,
            'login_form_action' => 'https://shilen.gov.mn/do-login',
            'login_username_field' => 'user',
            'login_password_field' => 'pass',
        ]);
        $this->withCredential($system, $user);

        $this->actingAs($user);
        $this->withSession([Vault::SESSION_KEY => now()->addHour()->timestamp]);

        $this->get(route('systems.launch', $system))
            ->assertOk()
            ->assertSee('action="https://shilen.gov.mn/do-login"', false)
            ->assertSee('name="user"', false)
            ->assertSee('name="pass"', false);
    }

    public function test_incomplete_form_post_config_falls_back_to_manual(): void
    {
        $user = User::factory()->create();
        $system = $this->system([
            'login_method' => System::LOGIN_FORM_POST,
            'login_form_action' => 'https://shilen.gov.mn/do-login',
            // талбарын нэрс дутуу
        ]);
        $this->withCredential($system, $user);

        $this->assertFalse($system->canAutoSubmit());

        $this->actingAs($user);
        $this->withSession([Vault::SESSION_KEY => now()->addHour()->timestamp]);

        $this->get(route('systems.launch', $system))
            ->assertOk()
            ->assertSee('Хуулах');
    }

    public function test_launch_records_last_used(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $credential = $this->withCredential($system, $user);

        $this->assertNull($credential->last_used_at);

        $this->actingAs($user);
        $this->withSession([Vault::SESSION_KEY => now()->addHour()->timestamp]);
        $this->get(route('systems.launch', $system));

        $this->assertNotNull($credential->refresh()->last_used_at);
    }

    public function test_launch_fails_without_a_saved_credential(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $system->viewers()->sync([$user->id]);

        $this->actingAs($user);
        $this->withSession([Vault::SESSION_KEY => now()->addHour()->timestamp]);

        $this->get(route('systems.launch', $system))->assertNotFound();
    }

    public function test_a_user_cannot_launch_with_another_users_credential(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $system = $this->system();
        $this->withCredential($system, $owner);
        $system->viewers()->syncWithoutDetaching([$other->id]);

        $this->actingAs($other);
        $this->withSession([Vault::SESSION_KEY => now()->addHour()->timestamp]);

        $this->get(route('systems.launch', $system))->assertNotFound();
    }

    public function test_an_expired_unlock_is_treated_as_locked(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $this->withCredential($system, $user);

        $this->actingAs($user);
        $this->withSession([Vault::SESSION_KEY => now()->subMinute()->timestamp]);

        $this->get(route('systems.launch', $system))->assertStatus(423);
    }

    public function test_locking_the_vault_blocks_further_launches(): void
    {
        $user = User::factory()->create(['password' => bcrypt('account-pw')]);
        $system = $this->system();
        $this->withCredential($system, $user);

        $this->actingAs($user);
        $this->postJson(route('vault.unlock'), ['account_password' => 'account-pw'])->assertOk();
        $this->get(route('systems.launch', $system))->assertOk();

        $this->postJson(route('vault.lock'))->assertOk();
        $this->get(route('systems.launch', $system))->assertStatus(423);
    }

    public function test_guests_cannot_launch_or_unlock(): void
    {
        $system = $this->system();

        $this->get(route('systems.launch', $system))->assertRedirect(route('login'));
        $this->postJson(route('vault.unlock'), ['account_password' => 'x'])->assertStatus(401);
    }
}
