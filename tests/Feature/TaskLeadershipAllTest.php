<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskLeadershipAllTest extends TestCase
{
    use RefreshDatabase;

    public function test_niit_combines_the_three_leadership_sections(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $governor = TaskSource::query()->where('key', 'governor')->firstOrFail();
        $deputy = TaskSource::query()->where('key', 'vice_governor')->firstOrFail();
        $chief = TaskSource::query()->where('key', 'chief_of_staff')->firstOrFail();

        Task::create([
            'task_source_id' => $deputy->id,
            'text' => 'Орлогчийн үүрэг',
            'sort_order' => 1,
            'progress' => 0,
        ]);
        Task::create([
            'task_source_id' => $governor->id,
            'text' => 'Даргагийн үүрэг',
            'sort_order' => 1,
            'progress' => 10,
        ]);
        Task::create([
            'task_source_id' => $chief->id,
            'text' => 'Тамгын газрын үүрэг',
            'sort_order' => 1,
            'progress' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('tasks.index', ['kind' => TaskSource::KEY_LEADERSHIP_ALL]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->where('kind', TaskSource::KEY_LEADERSHIP_ALL)
                ->has('tasks', 3)
                ->where('tasks.0.text', 'Даргагийн үүрэг')
                ->where('tasks.0.source_label', TaskSource::LEADERSHIP['governor'])
                ->where('tasks.1.text', 'Орлогчийн үүрэг')
                ->where('tasks.1.source_label', TaskSource::LEADERSHIP['vice_governor'])
                ->where('tasks.2.text', 'Тамгын газрын үүрэг')
                ->where('tasks.2.source_label', TaskSource::LEADERSHIP['chief_of_staff'])
            );
    }
}
