<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskDashboardSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_carries_people_and_progress(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'org_name' => 'Онцгой байдлын газар',
            'category' => 'agentlag',
            'person_name' => 'Ц.Мөнх-Эрдэнэ',
            'position' => 'Дарга',
            'org_order' => 1,
            'sort_order' => 1,
        ]);

        $source = TaskSource::query()->where('key', 'directive')->firstOrFail();
        $source->tasks()->create(['text' => 'Тест 1', 'responsible' => 'Ц.Мөнх-Эрдэнэ', 'progress' => 60, 'sort_order' => 1]);
        $source->tasks()->create(['text' => 'Тест 2', 'responsible' => 'Ц.Мөнх-Эрдэнэ', 'progress' => 100, 'sort_order' => 2]);

        $this->actingAs($admin)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->where('tasks.0.progress', 60)
                ->where('tasks.1.progress', 100)
                ->where('people.0.value', 'Ц.Мөнх-Эрдэнэ')
                ->where('people.0.category', 'agentlag'));

        $this->assertSame(2, Task::query()->count());
    }
}
