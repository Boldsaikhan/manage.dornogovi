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

    public function test_admin_can_register_a_new_system(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.systems.store'), [
                'name' => 'Шинэ систем',
                'url' => 'https://example.gov.mn',
                'login_method' => System::LOGIN_MANUAL,
                'is_active' => true,
                'requires_login' => true,
                'is_internal' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('systems', [
            'name' => 'Шинэ систем',
            'url' => 'https://example.gov.mn',
        ]);
    }

    public function test_admin_can_delete_a_system(): void
    {
        $system = $this->system();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->delete(route('admin.systems.destroy', $system))
            ->assertRedirect();

        $this->assertDatabaseMissing('systems', ['id' => $system->id]);
    }

    public function test_admin_can_reorder_systems_for_menu(): void
    {
        $first = System::create([
            'slug' => 'alpha',
            'name' => 'Alpha',
            'url' => 'https://alpha.example.gov.mn',
            'category' => 'Гадны',
            'sort_order' => 1,
        ]);
        $second = System::create([
            'slug' => 'beta',
            'name' => 'Beta',
            'url' => 'https://beta.example.gov.mn',
            'category' => 'Гадны',
            'sort_order' => 2,
        ]);
        $third = System::create([
            'slug' => 'gamma',
            'name' => 'Gamma',
            'url' => 'https://gamma.example.gov.mn',
            'category' => 'Гадны',
            'sort_order' => 3,
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch(route('admin.systems.reorder'), [
                'ids' => [$third->id, $first->id, $second->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, $third->refresh()->sort_order);
        $this->assertSame(2, $first->refresh()->sort_order);
        $this->assertSame(3, $second->refresh()->sort_order);

        $this->actingAs(User::factory()->create())
            ->get(route('dept.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('nav.0.name', 'Gamma')
                ->where('nav.1.name', 'Alpha')
                ->where('nav.2.name', 'Beta')
            );
    }

    public function test_menu_order_ignores_internal_systems(): void
    {
        $externalA = System::create([
            'slug' => 'ext-a',
            'name' => 'External A',
            'url' => 'https://a.example.gov.mn',
            'category' => 'Гадны',
            'is_internal' => false,
            'sort_order' => 1,
        ]);
        System::create([
            'slug' => 'int-x',
            'name' => 'Internal X',
            'url' => 'https://x.example.gov.mn',
            'category' => 'Дотоод',
            'is_internal' => true,
            'sort_order' => 2,
        ]);
        $externalB = System::create([
            'slug' => 'ext-b',
            'name' => 'External B',
            'url' => 'https://b.example.gov.mn',
            'category' => 'Гадны',
            'is_internal' => false,
            'sort_order' => 3,
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->patch(route('admin.systems.reorder'), [
                'ids' => [$externalB->id, $externalA->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, $externalB->refresh()->sort_order);
        $this->assertSame(2, $externalA->refresh()->sort_order);

        $this->actingAs(User::factory()->create())
            ->get(route('dept.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('nav.0.name', 'External B')
                ->where('nav.0.is_internal', false)
                ->where('nav.1.name', 'External A')
                ->where('nav.2.name', 'Internal X')
                ->where('nav.2.is_internal', true)
            );
    }

    public function test_non_admin_cannot_reorder_systems(): void
    {
        $system = $this->system();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->patch(route('admin.systems.reorder'), [
                'ids' => [$system->id],
            ])
            ->assertForbidden();
    }
}
