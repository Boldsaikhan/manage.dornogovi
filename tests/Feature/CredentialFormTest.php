<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use App\Models\UserCredential;
use App\Support\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CredentialFormTest extends TestCase
{
    use RefreshDatabase;

    private function system(): System
    {
        return System::create([
            'slug' => 'shuudan',
            'name' => 'Төрийн шуудан',
            'url' => 'https://mail.example.mn',
            'login_method' => System::LOGIN_MANUAL,
            'requires_login' => true,
            'is_active' => true,
            'is_internal' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_saved_username_is_shown_but_password_is_not(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $system->viewers()->attach($user->id);

        UserCredential::create([
            'user_id' => $user->id,
            'system_id' => $system->id,
            'username_encrypted' => 'bold@dornogovi.gov.mn',
            'password_encrypted' => 'nuuts-ug',
        ]);

        $this->actingAs($user);
        $this->withSession([Vault::SESSION_KEY => now()->addHour()->timestamp]);

        $response = $this->get(route('systems.show', $system->id));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('system.saved_username', 'bold@dornogovi.gov.mn')
            ->where('system.has_credential', true)
        );

        // Нууц үг хуудсанд огт очихгүй.
        $response->assertDontSee('nuuts-ug');
    }

    public function test_username_is_hidden_while_the_vault_is_locked(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $system->viewers()->attach($user->id);

        UserCredential::create([
            'user_id' => $user->id,
            'system_id' => $system->id,
            'username_encrypted' => 'bold@dornogovi.gov.mn',
            'password_encrypted' => 'nuuts-ug',
        ]);

        $this->actingAs($user);
        $this->withSession([Vault::SESSION_KEY => now()->subMinute()->timestamp]);

        $this->get(route('systems.show', $system->id))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('system.saved_username', null));
    }

    public function test_dan_system_defaults_to_the_dan_method(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $system->forceFill(['supports_dan' => true])->save();
        $system->viewers()->attach($user->id);

        // Мэдээлэл хадгалаагүй — ДАН-аар нэвтэрдэг тул ДАН нь анхны сонголт.
        $this->actingAs($user)
            ->get(route('systems.show', $system->id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('system.supports_dan', true)
                ->where('system.auth_type', System::AUTH_DAN)
            );
    }

    public function test_saved_method_wins_over_the_default(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $system->forceFill(['supports_dan' => true])->save();
        $system->viewers()->attach($user->id);

        UserCredential::create([
            'user_id' => $user->id,
            'system_id' => $system->id,
            'auth_type' => System::AUTH_PASSWORD,
            'username_encrypted' => 'ner',
            'password_encrypted' => 'nuuts',
        ]);

        $this->actingAs($user)
            ->get(route('systems.show', $system->id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('system.auth_type', System::AUTH_PASSWORD)
            );
    }

    public function test_saving_without_a_password_keeps_the_stored_one(): void
    {
        $user = User::factory()->create();
        $system = $this->system();

        UserCredential::create([
            'user_id' => $user->id,
            'system_id' => $system->id,
            'username_encrypted' => 'huuchin',
            'password_encrypted' => 'nuuts-ug',
        ]);

        $this->actingAs($user)
            ->post(route('credentials.store'), [
                'system_id' => $system->id,
                'username' => 'shine-ner',
                'password' => '',
            ])
            ->assertSessionHasNoErrors();

        $credential = UserCredential::firstOrFail();

        $this->assertSame('shine-ner', $credential->username_encrypted);
        $this->assertSame('nuuts-ug', $credential->password_encrypted);
    }

    public function test_first_time_save_still_requires_a_password(): void
    {
        $user = User::factory()->create();
        $system = $this->system();

        $this->actingAs($user)
            ->post(route('credentials.store'), [
                'system_id' => $system->id,
                'username' => 'ner',
                'password' => '',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('user_credentials', 0);
    }
}
