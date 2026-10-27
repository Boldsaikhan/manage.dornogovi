<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemCredentialModalTest extends TestCase
{
    use RefreshDatabase;

    private function system(): System
    {
        return System::create([
            'name' => 'Төрийн ERP',
            'slug' => 'toriin-erp',
            'url' => 'https://erp.example.mn',
            'is_active' => true,
            'requires_login' => true,
        ]);
    }

    public function test_a_viewer_reads_the_credential_settings_as_json(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $system->viewers()->attach($user->id);

        $this->actingAs($user)
            ->getJson("/systems/{$system->id}/credential")
            ->assertOk()
            ->assertJsonPath('id', $system->id)
            ->assertJsonPath('name', 'Төрийн ERP')
            ->assertJsonPath('has_credential', false);
    }

    public function test_someone_without_access_is_refused(): void
    {
        $system = $this->system();

        $this->actingAs(User::factory()->create())
            ->getJson("/systems/{$system->id}/credential")
            ->assertForbidden();
    }
}
