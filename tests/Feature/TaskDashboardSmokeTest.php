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

        PhoneDirectoryEntry::create([
            'org_name' => 'Хөрөнгө оруулалт, хөгжлийн бодлого төлөвлөлтийн хэлтэс',
            'category' => 'heltes',
            'person_name' => 'Б.Дулмаа',
            'position' => 'Мэргэжилтэн',
            'org_order' => 2,
            'sort_order' => 1,
        ]);
        PhoneDirectoryEntry::create([
            'org_name' => 'Хөрөнгө оруулалт, хөгжлийн бодлого төлөвлөлтийн хэлтэс',
            'category' => 'heltes',
            'person_name' => 'А.Болд',
            'position' => 'Мэргэжилтэн',
            'org_order' => 2,
            'sort_order' => 2,
        ]);

        $source = TaskSource::query()->where('key', 'directive')->firstOrFail();
        $source->tasks()->create(['text' => 'Тест 1', 'responsible' => 'Ц.Мөнх-Эрдэнэ', 'progress' => 60, 'sort_order' => 1]);
        $source->tasks()->create(['text' => 'Тест 2', 'responsible' => 'Ц.Мөнх-Эрдэнэ', 'progress' => 100, 'sort_order' => 2]);
        $source->tasks()->create(['text' => 'Хэлтэс A', 'responsible' => 'Б.Дулмаа', 'progress' => 20, 'sort_order' => 3]);
        $source->tasks()->create(['text' => 'Хэлтэс B', 'responsible' => 'Б.Дулмаа', 'progress' => 40, 'sort_order' => 4]);
        $source->tasks()->create(['text' => 'Хэлтэс C', 'responsible' => 'А.Болд', 'progress' => 80, 'sort_order' => 5]);

        $this->actingAs($admin)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->where('tasks.0.progress', 60)
                ->where('tasks.1.progress', 100)
                ->has('people', 3)
                ->where('people.0.category', fn ($c) => in_array($c, ['agentlag', 'heltes'], true)));

        $this->assertSame(5, Task::query()->count());
    }
}
