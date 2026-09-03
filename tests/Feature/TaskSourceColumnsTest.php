<?php

namespace Tests\Feature;

use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskSourceColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_section_is_created_with_the_chosen_table_headings(): void
    {
        $columns = ['measure', 'responsible', 'period', 'note', 'collaborator'];

        $this->actingAs($this->admin())
            ->post(route('tasks.sources.store'), [
                'name' => 'Аймгийн хөгжлийн төлөвлөгөө',
                'columns' => $columns,
            ])
            ->assertRedirect();

        $source = TaskSource::query()->where('name', 'Аймгийн хөгжлийн төлөвлөгөө')->firstOrFail();

        // Сонгосон талбарууд каталогийн дарааллаар хадгалагдана.
        $this->assertSame(
            TaskSource::normalizeColumnKeys($columns),
            $source->columnKeyList(),
        );

        $labels = collect($source->resolvedColumns())->pluck('label')->all();

        foreach (['Арга хэмжээ', 'Хариуцах эзэн', 'Хугацаа', 'Хэрэгжилт', 'Хяналт тавих'] as $label) {
            $this->assertContains($label, $labels);
        }

        // Сонгоогүй талбар хүснэгтэд гарахгүй.
        $this->assertNotContains('Ажлын чиглэл', $labels);
    }

    public function test_index_exposes_columns_and_choices(): void
    {
        $this->actingAs($this->admin())
            ->post(route('tasks.sources.store'), [
                'name' => 'Тест хэсэг',
                'columns' => ['measure', 'responsible', 'period'],
            ]);

        $source = TaskSource::query()->where('name', 'Тест хэсэг')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('tasks.index', ['kind' => $source->key]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->has('source.columns', 3)
                ->where('source.columns.0.key', 'measure')
                ->has('columnChoices')
            );
    }

    public function test_chosen_column_order_is_kept(): void
    {
        // Каталогийн дараалал: sector, measure, text, period, responsible, collaborator, note
        // Хэрэглэгч өөр дарааллаар сонгосон бол яг тэр дарааллаар нь хадгална.
        $columns = ['note', 'responsible', 'measure'];

        $this->actingAs($this->admin())
            ->post(route('tasks.sources.store'), [
                'name' => 'Дараалал тест',
                'columns' => $columns,
            ])
            ->assertRedirect();

        $source = TaskSource::query()->where('name', 'Дараалал тест')->firstOrFail();

        $this->assertSame($columns, $source->columnKeyList());

        $this->assertSame(
            ['Хэрэгжилт', 'Хариуцах эзэн', 'Арга хэмжээ'],
            collect($source->resolvedColumns())->pluck('label')->all(),
        );
    }

    public function test_at_least_one_column_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('tasks.sources.store'), [
                'name' => 'Хоосон хэсэг',
                'columns' => [],
            ])
            ->assertSessionHasErrors('columns');

        $this->assertDatabaseMissing('task_sources', ['name' => 'Хоосон хэсэг']);
    }

    public function test_unknown_column_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('tasks.sources.store'), [
                'name' => 'Буруу хэсэг',
                'columns' => ['measure', 'baihgui_talbar'],
            ])
            ->assertSessionHasErrors('columns.1');
    }
}
