<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PhoneDirectoryEntry;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Нэгжийн хэсэгт мөр засах бүх үйлдэл алдаагүй ажиллах ёстой.
 */
class UnitTaskEditingTest extends TestCase
{
    use RefreshDatabase;

    private function unit(): Department
    {
        return Department::create([
            'name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'code' => 'tzuh',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function task(TaskSource $source): Task
    {
        return Task::create([
            'task_source_id' => $source->id,
            'text' => 'Туршилтын үүрэг',
            'responsible' => 'Б.Болдсайхан',
            'collaborator' => 'Ц.Сансармаа',
            'sort_order' => 1,
        ]);
    }

    /** @return array{0: User, 1: Task} */
    private function scene(string $level = 'manage'): array
    {
        $unit = $this->unit();

        $user = User::factory()->create([
            'name' => 'Б.Болдсайхан',
            'department_id' => $unit->id,
            'phone' => '89239655',
        ]);
        $this->grantModule($user, 'tasks', $level);

        PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'person_name' => 'Балдандоржийн Болдсайхан',
            'mobile_phone' => '89239655',
            'sort_order' => 1,
            'org_order' => 1,
        ]);

        $source = TaskSource::where('key', 'unit_01')->firstOrFail();

        return [$user->fresh(), $this->task($source)];
    }

    public function test_every_field_can_be_edited_on_a_unit_section(): void
    {
        [$user, $task] = $this->scene();

        foreach ([
            'text' => 'Шинэчилсэн үүрэг',
            'measure' => 'Арга хэмжээ',
            'period' => '10.28-10.30',
            'sector' => 'Захиргаа',
            'note' => 'Хийгдэж байна',
            'responsible' => 'Ц.Сансармаа',
            'collaborator' => 'Б.Болдсайхан',
        ] as $field => $value) {
            $this->actingAs($user)
                ->patch(route('tasks.update', $task), [$field => $value])
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->patch(route('tasks.update', $task), ['progress' => 100])
            ->assertRedirect();

        $fresh = $task->fresh();

        $this->assertSame('Хийгдэж байна', $fresh->note);
        $this->assertSame(100, (int) $fresh->progress);
    }

    public function test_the_page_still_loads_after_editing(): void
    {
        [$user, $task] = $this->scene();

        $this->actingAs($user)
            ->patch(route('tasks.update', $task), ['note' => 'Тэмдэглэл'])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'unit_01']))
            ->assertOk();
    }

    public function test_a_row_can_be_added_and_deleted(): void
    {
        [$user] = $this->scene();

        $this->actingAs($user)
            ->post(route('tasks.store'), ['kind' => 'unit_01', 'text' => 'Шинэ мөр'])
            ->assertRedirect();

        $added = Task::where('text', 'Шинэ мөр')->firstOrFail();

        $this->actingAs($user)
            ->delete(route('tasks.destroy', $added))
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $added->id]);
    }

    public function test_a_unit_manager_can_edit_the_same_rows(): void
    {
        [$user, $task] = $this->scene('manage_unit');

        $this->actingAs($user)
            ->patch(route('tasks.update', $task), ['note' => 'Хэлтсийн эрхээр'])
            ->assertRedirect();

        $this->assertSame('Хэлтсийн эрхээр', $task->fresh()->note);

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'unit_01']))
            ->assertOk();
    }
}
