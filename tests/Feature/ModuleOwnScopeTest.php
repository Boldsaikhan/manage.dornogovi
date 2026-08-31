<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\Department;
use App\Models\Leave;
use App\Models\Regulation;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleOwnScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_own_sees_only_related_leaves(): void
    {
        $viewer = User::factory()->create(['name' => 'Б. Болдсайхан']);
        UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => 'leaves',
            'level' => 'view_own',
        ]);

        Leave::create([
            'user_id' => $viewer->id,
            'person_name' => 'Б. Болдсайхан',
            'scope' => 'baiguullaga',
            'org_name' => 'Тест',
            'type' => 'eeljiin',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days' => 1,
            'status' => 'approved',
        ]);

        Leave::create([
            'user_id' => User::factory()->create()->id,
            'person_name' => 'Д. Баттуяа',
            'scope' => 'baiguullaga',
            'org_name' => 'Тест',
            'type' => 'eeljiin',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days' => 1,
            'status' => 'approved',
        ]);

        $this->actingAs($viewer)
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.person_name', 'Б. Болдсайхан'));
    }

    public function test_admin_can_save_own_scope_permission_on_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'permissions' => ['tasks' => 'view_own', 'regulations' => 'view_own'],
            ])
            ->assertRedirect();

        $this->assertSame('view_own', UserModulePermission::query()
            ->where('user_id', $user->id)
            ->where('module_key', 'tasks')
            ->value('level'));

        $this->assertSame('view_own', UserModulePermission::query()
            ->where('user_id', $user->id)
            ->where('module_key', 'regulations')
            ->value('level'));
    }

    public function test_role_template_accepts_own_scope_levels(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.roles.update', 'specialist'), [
                'permissions' => ['tasks' => 'manage_own', 'leaves' => 'view_own', 'regulations' => 'view_own'],
            ])
            ->assertRedirect();

        $this->assertSame('manage_own', \App\Models\RolePermission::query()
            ->where('role', 'specialist')
            ->where('module_key', 'tasks')
            ->value('level'));
        $this->assertSame('view_own', \App\Models\RolePermission::query()
            ->where('role', 'specialist')
            ->where('module_key', 'regulations')
            ->value('level'));
    }

    public function test_tasks_index_accepts_relation_query_without_type_error(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        \App\Models\TaskSource::query()->firstOrCreate(
            ['key' => \App\Models\TaskSource::KEY_DIRECTIVE],
            ['name' => 'Удирдамж', 'sort_order' => 1],
        );

        $this->actingAs($user)
            ->get(route('tasks.index'))
            ->assertOk();
    }

    public function test_specialist_with_regulations_view_own_sees_all_documents(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $specialist = User::factory()->create([
            'is_admin' => false,
            'is_specialist' => true,
            'is_department_head' => false,
        ]);

        RolePermission::replaceFor('specialist', ['regulations' => 'view_own']);
        UserModulePermission::create([
            'user_id' => $specialist->id,
            'module_key' => 'regulations',
            'level' => 'view_own',
        ]);

        Regulation::create([
            'title' => 'Кибер аюулгүй байдлын журам',
            'category' => 'cyber_security',
            'created_by' => $admin->id,
            'published_at' => now(),
        ]);

        $this->actingAs($specialist)
            ->get(route('regulations.index', ['scope' => 'cyber_security']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.title', 'Кибер аюулгүй байдлын журам'));
    }

    public function test_access_page_exposes_decrees_own_scope(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('modules')
                ->where('modules', fn ($modules) => collect($modules)
                    ->contains(fn ($m) => ($m['key'] ?? null) === 'decrees' && ($m['own_scope'] ?? false) === true)));
    }

    public function test_role_template_saves_decrees_own_scope_levels(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.roles.update', 'specialist'), [
                'permissions' => ['decrees' => 'view_own'],
            ])
            ->assertRedirect();

        $this->assertSame('view_own', RolePermission::query()
            ->where('role', 'specialist')
            ->where('module_key', 'decrees')
            ->value('level'));

        $this->actingAs($admin)
            ->patch(route('admin.roles.update', 'specialist'), [
                'permissions' => ['decrees' => 'edit_own'],
            ])
            ->assertRedirect();

        $this->assertSame('edit_own', RolePermission::query()
            ->where('role', 'specialist')
            ->where('module_key', 'decrees')
            ->value('level'));
    }

    public function test_user_with_view_own_sees_only_own_decrees(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => 'decrees',
            'level' => 'view_own',
        ]);

        Decree::query()->create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Өөрийн захирамж',
            'created_by' => $viewer->id,
        ]);
        Decree::query()->create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '02',
            'title' => 'Бусдын захирамж',
            'created_by' => $other->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.title', 'Өөрийн захирамж'));
    }

    public function test_edit_own_cannot_update_or_delete_others_decrees(): void
    {
        $editor = User::factory()->create();
        $other = User::factory()->create();
        UserModulePermission::create([
            'user_id' => $editor->id,
            'module_key' => 'decrees',
            'level' => 'edit_own',
        ]);

        $own = Decree::query()->create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Миний',
            'created_by' => $editor->id,
        ]);
        $foreign = Decree::query()->create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '02',
            'title' => 'Бусдын',
            'created_by' => $other->id,
        ]);

        $this->actingAs($editor)
            ->patch(route('decrees.update', $own), ['title' => 'Шинэчилсэн'])
            ->assertRedirect();
        $this->assertSame('Шинэчилсэн', $own->fresh()->title);

        $this->actingAs($editor)
            ->patch(route('decrees.update', $foreign), ['title' => 'Хакед'])
            ->assertForbidden();
        $this->assertSame('Бусдын', $foreign->fresh()->title);

        $this->actingAs($editor)
            ->delete(route('decrees.destroy', $foreign))
            ->assertForbidden();
        $this->assertDatabaseHas('decrees', ['id' => $foreign->id]);
    }

    public function test_access_page_exposes_dashboard_own_scope_view_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('modules')
                ->where('modules', fn ($modules) => collect($modules)->contains(fn ($m) => ($m['key'] ?? null) === 'dept_dashboard'
                    && ($m['own_scope'] ?? false) === true
                    && ($m['own_levels'] ?? null) === ['view_own'])));
    }

    public function test_role_template_saves_dashboard_view_own(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.roles.update', 'specialist'), [
                'permissions' => ['dept_dashboard' => 'view_own'],
            ])
            ->assertRedirect();

        $this->assertSame('view_own', RolePermission::query()
            ->where('role', 'specialist')
            ->where('module_key', 'dept_dashboard')
            ->value('level'));
    }

    public function test_dashboard_view_own_shows_only_related_leaves(): void
    {
        $department = Department::query()->create([
            'name' => 'Тест хэлтэс',
            'code' => 'TST',
            'is_active' => true,
        ]);

        $viewer = User::factory()->create([
            'name' => 'Б. Болдсайхан',
            'department_id' => $department->id,
        ]);
        $other = User::factory()->create([
            'name' => 'Д. Баттуяа',
            'department_id' => $department->id,
        ]);

        UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => 'dept_dashboard',
            'level' => 'view_own',
        ]);
        UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => 'leaves',
            'level' => 'manage',
        ]);

        Leave::create([
            'user_id' => $viewer->id,
            'department_id' => $department->id,
            'person_name' => 'Б. Болдсайхан',
            'scope' => 'baiguullaga',
            'org_name' => 'Тест',
            'type' => 'eeljiin',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);
        Leave::create([
            'user_id' => $other->id,
            'department_id' => $department->id,
            'person_name' => 'Д. Баттуяа',
            'scope' => 'baiguullaga',
            'org_name' => 'Тест',
            'type' => 'eeljiin',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);

        $this->actingAs($viewer)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.pending_leaves', 1)
                ->has('recentLeaves', 1)
                ->where('recentLeaves.0.person_name', 'Б. Болдсайхан'));
    }
}
