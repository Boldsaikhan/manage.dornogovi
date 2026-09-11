<?php

namespace Tests\Feature;

use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Үүрэг даалгаварын өөрчлөлтийн түүх.
 */
class TaskAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function source(string $key = 'directive'): TaskSource
    {
        return TaskSource::query()->firstOrCreate(
            ['key' => $key],
            ['name' => 'Үүрэг чиглэл', 'layout' => 'directive'],
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function logs(User $user, string $kind = 'directive'): array
    {
        return $this->actingAs($user)
            ->getJson(route('tasks.logs', ['kind' => $kind]))
            ->assertOk()
            ->json('rows');
    }

    public function test_adding_editing_and_deleting_are_recorded(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Б.Болдсайхан']);
        $source = $this->source();

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->post(route('tasks.store'), ['kind' => 'directive', 'text' => 'Анхны үүрэг']);

        $task = $source->tasks()->get()->last();

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->patch(route('tasks.update', $task), ['period' => '08.01–09.30']);

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->delete(route('tasks.destroy', $task));

        $rows = $this->logs($admin);

        $this->assertSame(
            ['Устгасан', 'Засварласан', 'Шинээр нэмсэн'],
            array_column($rows, 'action_label'),
        );

        $this->assertSame('Б.Болдсайхан', $rows[0]['user']);
        $this->assertSame('Анхны үүрэг', $rows[0]['label']);

        // Засварласан мөрөнд хуучин, шинэ утга хоёулаа үлдэнэ.
        $this->assertSame(
            ['from' => '', 'to' => '08.01–09.30'],
            $rows[1]['changes']['Хугацаа'],
        );
    }

    public function test_the_history_is_kept_per_tab(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->source();
        $this->source('prep_plan');

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->post(route('tasks.store'), ['kind' => 'directive', 'text' => 'Зөвхөн энэ табд']);

        $this->assertCount(1, $this->logs($admin, 'directive'));
        $this->assertCount(0, $this->logs($admin, 'prep_plan'));
    }

    public function test_a_viewer_without_access_is_refused(): void
    {
        $stranger = User::factory()->create(['is_admin' => false]);

        // Модулийг харах эрхгүй хэрэглэгч түүхийг ч харахгүй.
        $this->actingAs($stranger)
            ->getJson(route('tasks.logs', ['kind' => 'directive']))
            ->assertForbidden();
    }
}
