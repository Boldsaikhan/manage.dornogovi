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
        PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'category' => 'heltes',
            'person_name' => 'Д.Сарнай',
            'position' => 'Мэргэжилтэн',
            'org_order' => 3,
            'sort_order' => 1,
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
                ->has('people', 4)
                ->where('people.0.category', fn ($c) => in_array($c, ['agentlag', 'heltes'], true))
                ->where('people', fn ($people) => collect($people)->contains(
                    fn ($p) => ($p['label'] ?? '') === 'Ц.Мөнх-Эрдэнэ'
                        && ($p['full'] ?? '') === 'Ц.Мөнх-Эрдэнэ'
                )));

        // Утасны жагсаалтад 2 хэлтэс тэмдэглэгдсэн.
        $heltesOrgs = collect(\App\Models\PhoneDirectoryEntry::query()->where('category', 'heltes')->pluck('org_name'))->unique();
        $this->assertCount(2, $heltesOrgs);

        $this->assertSame(5, Task::query()->count());
    }
}
