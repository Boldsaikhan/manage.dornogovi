<?php

namespace Tests\Feature;

use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Үүрэг чиглэлийг хүснэгтэд шууд мөр болгон нэмэх.
 */
class TaskRowAddTest extends TestCase
{
    use RefreshDatabase;

    private function source(): TaskSource
    {
        return TaskSource::query()->firstOrCreate(
            ['key' => 'directive'],
            ['name' => 'Үүрэг чиглэл', 'layout' => 'directive'],
        );
    }

    public function test_an_empty_row_can_be_added(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = $this->source();

        // Зөвхөн төрлийг илгээнэ — бусад талбар хоосон.
        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->post(route('tasks.store'), ['kind' => 'directive'])
            ->assertRedirect();

        $this->assertSame(1, $source->tasks()->count());
    }

    public function test_added_rows_keep_their_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = $this->source();

        $source->tasks()->create(['text' => 'Эхний', 'sort_order' => 1]);

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->post(route('tasks.store'), ['kind' => 'directive']);

        // Шинэ мөр жагсаалтын төгсгөлд орно.
        // Хамаарал нь sort_order-оор эрэмбэлэгддэг тул сүүлийнх нь шинэ мөр.
        $last = $source->tasks()->get()->last();

        $this->assertSame(2, $last->sort_order);
        $this->assertSame('', (string) $last->text);
    }

    public function test_a_viewer_cannot_add_a_row(): void
    {
        $viewer = User::factory()->create(['is_admin' => false]);
        $source = $this->source();

        $this->actingAs($viewer)
            ->post(route('tasks.store'), ['kind' => 'directive'])
            ->assertForbidden();

        $this->assertSame(0, $source->tasks()->count());
    }
}
