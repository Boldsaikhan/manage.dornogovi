<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use App\Support\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Нэгж тус бүрийн дотоодын үүрэг даалгавар — зөвхөн тухайн нэгжийнхэнд.
 */
class UnitTaskSourceTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $departmentName): User
    {
        $department = Department::create([
            'name' => $departmentName,
            'code' => substr(md5($departmentName), 0, 6),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['department_id' => $department->id]);

        $user->modulePermissions()->updateOrCreate(['module_key' => 'tasks'], ['level' => 'manage']);

        return $user->fresh();
    }

    private function tabKeys(User $user): array
    {
        $keys = [];

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('tasks.index'))
            ->assertInertia(function (AssertableInertia $page) use (&$keys) {
                $keys = collect($page->toArray()['props']['kinds'])->pluck('key')->all();
            });

        return $keys;
    }

    public function test_every_listed_unit_has_its_own_section(): void
    {
        $this->assertCount(12, TaskSource::UNITS);

        foreach (TaskSource::UNITS as $key => $name) {
            $this->assertDatabaseHas('task_sources', ['key' => $key, 'unit_name' => $name]);
        }
    }

    public function test_staff_only_see_their_own_units_section(): void
    {
        $user = $this->staff('Архивын тасаг');

        $keys = $this->tabKeys($user);

        $this->assertContains('unit_04', $keys);
        $this->assertNotContains('unit_01', $keys);
        $this->assertNotContains('unit_08', $keys);
    }

    public function test_the_unit_name_is_matched_even_with_numbering_and_commas(): void
    {
        $user = $this->staff('7. Цэргийн штаб');

        $this->assertContains('unit_10', $this->tabKeys($user));
    }

    public function test_an_admin_sees_every_unit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $keys = $this->tabKeys($admin);

        foreach (array_keys(TaskSource::UNITS) as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_staff_cannot_open_another_units_section(): void
    {
        $user = $this->staff('Архивын тасаг');

        // Өөр нэгжийн хэсгийг нээхийг оролдвол өөрт харагдах хэсэг рүү буцаана.
        $response = $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'unit_01']));

        $response->assertRedirect();
        $this->assertStringNotContainsString('unit_01', (string) $response->headers->get('Location'));
    }

    public function test_the_old_shared_section_shows_up_only_when_it_has_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertNotContains(TaskSource::KEY_DEPARTMENT, $this->tabKeys($admin));

        $source = TaskSource::query()->where('key', TaskSource::KEY_DEPARTMENT)->first();
        Task::create(['task_source_id' => $source->id, 'text' => 'Жишээ үүрэг']);

        $this->assertContains(TaskSource::KEY_DEPARTMENT, $this->tabKeys($admin));
    }

    public function test_the_people_picker_only_offers_that_units_staff(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'org_name' => 'Архивын тасаг',
            'person_name' => 'А.Урангоо',
            'position' => 'Мэдээллийн технологийн ажилтан',
        ]);

        \App\Models\PhoneDirectoryEntry::create([
            'org_name' => 'Нийгмийн бодлогын хэлтэс',
            'person_name' => 'А.Ханддорж',
            'position' => 'Соёл, спортын бодлого хариуцсан мэргэжилтэн',
        ]);

        $people = [];

        $this->actingAs($admin)
            ->get(route('tasks.index', ['kind' => 'unit_04']))
            ->assertInertia(function (AssertableInertia $page) use (&$people) {
                $people = collect($page->toArray()['props']['people'])->pluck('full')->all();
            });

        $this->assertContains('А.Урангоо', $people);
        $this->assertNotContains('А.Ханддорж', $people);
    }

    public function test_the_picker_falls_back_to_everyone_when_the_unit_has_nobody(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'org_name' => 'Нийгмийн бодлогын хэлтэс',
            'person_name' => 'А.Ханддорж',
            'position' => 'Мэргэжилтэн',
        ]);

        $people = [];

        // «Теле камерын хяналтын дэд төв»-д хэн ч бүртгэлгүй.
        $this->actingAs($admin)
            ->get(route('tasks.index', ['kind' => 'unit_12']))
            ->assertInertia(function (AssertableInertia $page) use (&$people) {
                $people = collect($page->toArray()['props']['people'])->pluck('full')->all();
            });

        $this->assertContains('А.Ханддорж', $people);
    }

    public function test_own_only_staff_still_see_their_units_section_and_all_its_tasks(): void
    {
        $user = $this->staff('Хэвлэл мэдээллийн алба');
        $user->modulePermissions()->updateOrCreate(['module_key' => 'tasks'], ['level' => 'view_own']);
        $user = $user->fresh();

        $source = TaskSource::where('key', 'unit_02')->firstOrFail();

        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Нэгжийн нийтийн үүрэг',
            'responsible' => 'Өөр хүн',
            'sort_order' => 1,
        ]);

        $this->assertContains('unit_02', $this->tabKeys($user));

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('tasks.index', ['kind' => 'unit_02']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('kind', 'unit_02')
                ->has('tasks', 1));
    }

    public function test_own_only_staff_do_not_see_other_units_section(): void
    {
        $user = $this->staff('Хэвлэл мэдээллийн алба');
        $user->modulePermissions()->updateOrCreate(['module_key' => 'tasks'], ['level' => 'view_own']);
        $user = $user->fresh();

        $this->assertNotContains('unit_03', $this->tabKeys($user));
    }

}
