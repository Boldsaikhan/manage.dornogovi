<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use App\Models\UserCredential;
use App\Support\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemLaunchTest extends TestCase
{
    use RefreshDatabase;

    private function system(): System
    {
        return System::create([
            'slug' => 'test-erp',
            'name' => 'Тест ERP',
            'url' => 'https://erp.example.mn',
            'login_method' => System::LOGIN_MANUAL,
            'requires_login' => true,
            'is_active' => true,
            'is_internal' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_locked_vault_sends_user_to_unlock_and_remembers_the_target(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $system->viewers()->attach($user->id);

        UserCredential::create([
            'user_id' => $user->id,
            'system_id' => $system->id,
            'username_encrypted' => 'hereglegch',
            'password_encrypted' => 'nuuts',
        ]);

        $this->actingAs($user)
            ->get(route('systems.launch', $system->id))
            ->assertRedirect(route('systems.show', $system->id))
            // Сан нээгдмэгц энэ систем рүү үргэлжлүүлэхийг санана.
            ->assertSessionHas('launch_after_unlock', $system->id);
    }

    public function test_unlocked_vault_launches_with_credentials(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $system->viewers()->attach($user->id);

        UserCredential::create([
            'user_id' => $user->id,
            'system_id' => $system->id,
            'username_encrypted' => 'hereglegch',
            'password_encrypted' => 'nuuts',
        ]);

        $this->actingAs($user);
        $this->withSession([Vault::SESSION_KEY => now()->addHour()->timestamp]);

        $response = $this->get(route('systems.launch', $system->id));

        $response->assertOk();
        $response->assertSee('hereglegch', false);
    }

    public function test_missing_credential_redirects_to_system_page(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $system->viewers()->attach($user->id);

        $this->actingAs($user)
            ->get(route('systems.launch', $system->id))
            ->assertRedirect(route('systems.show', $system->id));
    }
}
