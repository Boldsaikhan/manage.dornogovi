<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DepartmentDashboardTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_only_tasks_for_logged_in_specialist(): void
    {
        $user = User::factory()->create([
            'name' => 'Ариунболдын Бадрал',
            'is_admin' => true,
        ]);

        $source = TaskSource::where('key', TaskSource::KEY_DIRECTIVE)->first();
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Миний үүрэг',
            'responsible' => 'А.Бадрал',
            'progress' => 20,
            'sort_order' => 1,
        ]);
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Хамтран гүйцэтгэгч',
            'responsible' => 'П.Гантуяа',
            'collaborator' => 'А.Бадрал',
            'progress' => 0,
            'sort_order' => 2,
        ]);
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Бусдын үүрэг',
            'responsible' => 'Батмөнх',
            'progress' => 0,
            'sort_order' => 3,
        ]);

        $this->actingAs($user)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/DepartmentDashboard')
                ->where('stats.task_total', 2)
                ->has('recentTasks', 2)
                ->where('recentTasks.0.text', fn ($t) => in_array($t, ['Миний үүрэг', 'Хамтран гүйцэтгэгч'], true))
            );
    }

    public function test_dashboard_shows_open_task_notification_for_compact_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Б.Болдсайхан',
            'is_admin' => true,
        ]);

        $source = TaskSource::where('key', TaskSource::KEY_DIRECTIVE)->first();
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Самбарт харагдах үүрэг',
            'responsible' => 'Б. Болдсайхан',
            'progress' => 10,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/DepartmentDashboard')
                ->where('stats.task_total', 1)
                ->has('recentTasks', 1)
                ->where('notificationUnread', 1)
            );
    }
}
