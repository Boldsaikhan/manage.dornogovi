<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserModulePermission;
use App\Support\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Эрх зүйн акт, тогтоол бүлгийн бүтэц ба хандах эрх.
 */
class LegalStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shows_the_three_menus(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('legal-structure.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/LegalStructure')
                ->where('heading', 'Эрх зүйн акт, тогтоол')
                ->where('structure.0.label', 'Тайлан мэдээлэл')
                ->where('structure.1.label', 'Салбарын хууль')
                ->where('structure.2.label', 'Салбарын үзүүлэлт')
                ->where('canManage', true));
    }

    public function test_a_user_without_access_cannot_open_the_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('legal-structure.index'))
            ->assertForbidden();
    }

    public function test_access_for_the_three_menus_is_saved_separately(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $person = User::factory()->create(['is_specialist' => true, 'name' => 'Бат']);
        $this->grantModule($person, 'leaves', 'view');

        $this->assertTrue(ModuleAccess::canView($person->fresh(), 'reports'));

        $this->actingAs($admin)
            ->patch(route('legal-structure.update', $person), [
                'permissions' => [
                    'reports' => '',
                    'legal_acts' => 'view',
                    'sector_indicators' => 'edit',
                ],
            ])
            ->assertRedirect();

        $person->unsetRelation('modulePermissions');

        $this->assertFalse(ModuleAccess::canView($person, 'reports'));
        $this->assertTrue(ModuleAccess::canView($person, 'legal_acts'));
        $this->assertFalse(ModuleAccess::canEdit($person, 'legal_acts'));
        $this->assertTrue(ModuleAccess::canEdit($person, 'sector_indicators'));
        $this->assertFalse(ModuleAccess::canManage($person, 'sector_indicators'));
        $this->assertSame(
            'view',
            UserModulePermission::query()->where('user_id', $person->id)->where('module_key', 'leaves')->value('level'),
        );

        $this->actingAs($person)
            ->get(route('indicators.index'))
            ->assertOk();

        $this->actingAs($person)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_view_access_cannot_change_permissions(): void
    {
        $viewer = User::factory()->create();
        $this->grantModule($viewer, 'legal_structure', 'view');
        $person = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('legal-structure.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->patch(route('legal-structure.update', $person), [
                'permissions' => [
                    'reports' => 'manage',
                    'legal_acts' => 'manage',
                    'sector_indicators' => 'manage',
                ],
            ])
            ->assertForbidden();
    }
}
