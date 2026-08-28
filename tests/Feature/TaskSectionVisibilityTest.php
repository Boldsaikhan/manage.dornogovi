<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\RolePermission;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskSectionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_specialist_only_sees_sections_with_their_tasks(): void
    {
        RolePermission::replaceFor('specialist', [
            'tasks' => 'manage_own',
        ]);

        $user = User::factory()->create([
            'name' => 'Ариунболдын Бадрал',
            'is_admin' => false,
            'is_specialist' => true,
        ]);

        $directive = TaskSource::where('key', TaskSource::KEY_DIRECTIVE)->firstOrFail();
        $prep = TaskSource::where('key', TaskSource::KEY_PREP_PLAN)->firstOrFail();
        $training = TaskSource::query()->firstOrCreate(
            ['key' => 'surgaltyn_idevx_orolcoo'],
            [
                'name' => 'Сургалтын идэвх оролцоо',
                'layout' => TaskSource::KEY_DIRECTIVE,
                'sort_order' => 3,
            ],
        );

        Task::create([
            'task_source_id' => $directive->id,
            'text' => 'Бусдын үүрэг',
            'responsible' => 'П.Гантуяа',
            'progress' => 0,
            'sort_order' => 1,
        ]);
        Task::create([
            'task_source_id' => $prep->id,
            'text' => 'Бэлтгэл',
            'responsible' => 'Батмөнх',
            'progress' => 0,
            'sort_order' => 1,
        ]);
        Task::create([
            'task_source_id' => $training->id,
            'text' => 'сургалтад идэвхтэй оролцох',
            'responsible' => 'А.Бадрал',
            'collaborator' => 'АЗДТГ-ын дарга',
            'progress' => 0,
            'sort_order' => 1,
        ]);
        Task::create([
            'task_source_id' => $training->id,
            'text' => 'сургалтад идэвхтэй оролцох',
            'responsible' => 'П.Гантуяа',
            'progress' => 0,
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.index'))
            ->assertRedirect(route('tasks.index', ['kind' => 'surgaltyn_idevx_orolcoo']));

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'surgaltyn_idevx_orolcoo']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->has('kinds', 1)
                ->where('kinds.0.key', 'surgaltyn_idevx_orolcoo')
                ->has('tasks', 1)
                ->where('tasks.0.responsible', 'А.Бадрал')
                ->where('canEdit', true)
                ->where('canManage', true)
            );

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertRedirect(route('tasks.index', ['kind' => 'surgaltyn_idevx_orolcoo']));
    }

    public function test_specialist_sees_task_linked_via_phone_directory(): void
    {
        RolePermission::replaceFor('specialist', [
            'tasks' => 'manage_own',
        ]);

        $user = User::factory()->create([
            'name' => 'Буруу Нэр',
            'phone' => '89239655',
            'is_admin' => false,
            'is_specialist' => true,
        ]);

        PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'category' => 'heltes',
            'person_name' => 'Батбаярын Болдсайхан',
            'position' => 'Мэргэжилтэн',
            'mobile_phone' => '89239655',
            'org_order' => 1,
            'sort_order' => 1,
        ]);

        $training = TaskSource::query()->firstOrCreate(
            ['key' => 'surgaltyn_idevx_orolcoo'],
            [
                'name' => 'Сургалтын идэвх оролцоо',
                'layout' => TaskSource::KEY_DIRECTIVE,
                'sort_order' => 3,
            ],
        );

        Task::create([
            'task_source_id' => $training->id,
            'text' => 'сургалтад идэвхтэй оролцох',
            'responsible' => 'Б.Болдсайхан',
            'collaborator' => 'АЗДТГ-ын дарга',
            'progress' => 0,
            'sort_order' => 1,
        ]);
        Task::create([
            'task_source_id' => $training->id,
            'text' => 'сургалтад идэвхтэй оролцох',
            'responsible' => 'П.Гантуяа',
            'progress' => 0,
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'surgaltyn_idevx_orolcoo']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->has('tasks', 1)
                ->where('tasks.0.responsible', 'Б.Болдсайхан')
            );
    }

    public function test_user_access_all_level_overrides_specialist_own_scope(): void
    {
        RolePermission::replaceFor('specialist', [
            'tasks' => 'manage_own',
        ]);

        $user = User::factory()->create([
            'name' => 'А.Бадрал',
            'is_admin' => false,
            'is_specialist' => true,
        ]);
        UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'tasks',
            'level' => 'view',
        ]);

        $source = TaskSource::where('key', TaskSource::KEY_DIRECTIVE)->firstOrFail();
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Өөрийн',
            'responsible' => 'А.Бадрал',
            'progress' => 0,
            'sort_order' => 1,
        ]);
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Бусдын',
            'responsible' => 'П.Гантуяа',
            'progress' => 0,
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->has('tasks', 2)
                ->where('canEdit', false)
            );
    }
}
