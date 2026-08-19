<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function system(): System
    {
        return System::create([
            'slug' => 'shilen',
            'name' => 'Шилэн данс',
            'url' => 'https://shilen.gov.mn/home',
            'category' => 'Санхүү',
        ]);
    }

    public function test_only_admins_can_reach_system_settings(): void
    {
        $system = $this->system();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.systems.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.systems.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Systems')->has('systems', 1));

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->patch(route('admin.systems.update', $system), [])
            ->assertForbidden();
    }

    public function test_admin_can_configure_direct_login(): void
    {
        $system = $this->system();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch(route('admin.systems.update', $system), [
                'name' => 'Шилэн данс',
                'url' => 'https://shilen.gov.mn/home',
                'login_method' => System::LOGIN_FORM_POST,
                'login_form_action' => 'https://shilen.gov.mn/do-login',
                'login_username_field' => 'user',
                'login_password_field' => 'pass',
            ])
            ->assertRedirect();

        $this->assertTrue($system->refresh()->canAutoSubmit());
    }

    public function test_direct_login_requires_the_form_details(): void
    {
        $system = $this->system();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch(route('admin.systems.update', $system), [
                'name' => 'Шилэн данс',
                'url' => 'https://shilen.gov.mn/home',
                'login_method' => System::LOGIN_FORM_POST,
            ])
            ->assertSessionHasErrors(['login_form_action', 'login_username_field', 'login_password_field']);

        $this->assertFalse($system->refresh()->canAutoSubmit());
    }

    public function test_admin_can_recheck_embedding(): void
    {
        Http::fake(['*' => Http::response('', 200, ['X-Frame-Options' => 'DENY'])]);
        $system = $this->system();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.systems.check-embed', $system))
            ->assertRedirect();

        $this->assertFalse($system->refresh()->is_embeddable);
    }
}
