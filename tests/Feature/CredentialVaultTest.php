<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use App\Models\UserCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CredentialVaultTest extends TestCase
{
    use RefreshDatabase;

    private function system(): System
    {
        return System::create([
            'slug' => 'test-system',
            'name' => 'Туршилтын систем',
            'url' => 'https://example.mn',
            'category' => 'Туршилт',
        ]);
    }

    public function test_dashboard_lists_active_systems(): void
    {
        $user = User::factory()->create();
        $this->system();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('systems', 1)
                ->where('systems.0.name', 'Туршилтын систем')
                ->where('systems.0.has_credential', false)
                ->where('stats.saved', 0));
    }

    public function test_credential_is_stored_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $system = $this->system();

        $this->actingAs($user)->post(route('credentials.store'), [
            'system_id' => $system->id,
            'username' => 'bold@dornogovi.gov.mn',
            'password' => 'sZ7!secret',
        ])->assertRedirect();

        $raw = \DB::table('user_credentials')->first();

        $this->assertNotSame('sZ7!secret', $raw->password_encrypted);
        $this->assertStringNotContainsString('sZ7!secret', $raw->password_encrypted);
        $this->assertStringNotContainsString('bold@dornogovi.gov.mn', $raw->username_encrypted);

        $this->assertSame('sZ7!secret', UserCredential::first()->password_encrypted);
    }

    public function test_storing_twice_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $system = $this->system();

        foreach (['first', 'second'] as $password) {
            $this->actingAs($user)->post(route('credentials.store'), [
                'system_id' => $system->id,
                'username' => 'bold',
                'password' => $password,
            ]);
        }

        $this->assertSame(1, UserCredential::count());
        $this->assertSame('second', UserCredential::first()->password_encrypted);
    }

    public function test_reveal_requires_the_correct_account_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('account-pw')]);
        $system = $this->system();

        UserCredential::create([
            'user_id' => $user->id,
            'system_id' => $system->id,
            'username_encrypted' => 'bold',
            'password_encrypted' => 'system-pw',
        ]);

        $this->actingAs($user)
            ->postJson(route('credentials.reveal', $system), ['account_password' => 'wrong'])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('credentials.reveal', $system), ['account_password' => 'account-pw'])
            ->assertOk()
            ->assertJson(['username' => 'bold', 'password' => 'system-pw']);
    }

    public function test_a_user_cannot_reveal_another_users_credential(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create(['password' => bcrypt('account-pw')]);
        $system = $this->system();

        UserCredential::create([
            'user_id' => $owner->id,
            'system_id' => $system->id,
            'username_encrypted' => 'owner',
            'password_encrypted' => 'owner-pw',
        ]);

        $this->actingAs($other)
            ->postJson(route('credentials.reveal', $system), ['account_password' => 'account-pw'])
            ->assertStatus(404);
    }

    public function test_destroy_only_removes_the_current_users_credential(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $system = $this->system();

        foreach ([$owner, $other] as $u) {
            UserCredential::create([
                'user_id' => $u->id,
                'system_id' => $system->id,
                'username_encrypted' => 'x',
                'password_encrypted' => 'y',
            ]);
        }

        $this->actingAs($owner)
            ->delete(route('credentials.destroy', $system))
            ->assertRedirect();

        $this->assertSame(1, UserCredential::count());
        $this->assertSame($other->id, UserCredential::first()->user_id);
    }

    public function test_guests_cannot_touch_the_vault(): void
    {
        $system = $this->system();

        $this->post(route('credentials.store'), [])->assertRedirect(route('login'));
        $this->postJson(route('credentials.reveal', $system), [])->assertStatus(401);
    }
}
