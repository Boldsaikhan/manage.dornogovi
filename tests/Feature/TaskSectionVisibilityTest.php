<?php

namespace Tests\Feature;

use App\Models\RolePermission;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
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
}
