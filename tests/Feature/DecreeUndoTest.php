<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\EditUndo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecreeUndoTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_name_clears_the_whole_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $decree = Decree::create([
            'category' => 'blank',
            'kind' => 'blank',
            'title' => 'Б.Гантөмөр',
            'person_name' => 'Б.Гантөмөр',
            'qty_zahiramj' => 2,
            'num_zahiramj' => '1402-1403',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('decrees.update', $decree), ['person_name' => ''])
            ->assertRedirect();

        $fresh = $decree->fresh();
        $this->assertNull($fresh->person_name);
        $this->assertSame(0, $fresh->qty_zahiramj);
        $this->assertNull($fresh->num_zahiramj);
    }

    public function test_last_action_can_be_undone_after_reload(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $decree = Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Анхны гарчиг',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('decrees.update', $decree), ['title' => 'Өөрчилсөн гарчиг'])
            ->assertRedirect();

        $this->assertSame('Өөрчилсөн гарчиг', $decree->fresh()->title);
        $this->assertSame(1, EditUndo::query()->count());

        // Дахин ачаалсан ч түүх сервэрт хадгалагдана
        $this->actingAs($admin)->post(route('undo.store'))->assertRedirect();

        $this->assertSame('Анхны гарчиг', $decree->fresh()->title);
        $this->assertSame(0, EditUndo::query()->count());
    }

    public function test_history_keeps_only_last_ten(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $decree = Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Гарчиг 0',
            'created_by' => $admin->id,
        ]);

        for ($i = 1; $i <= 13; $i++) {
            $this->actingAs($admin)
                ->patch(route('decrees.update', $decree), ['title' => "Гарчиг {$i}"])
                ->assertRedirect();
        }

        $this->assertSame(EditUndo::KEEP, EditUndo::query()->count());

        // Сүүлийн үйлдлийг буцаахад өмнөх утга сэргэнэ
        $this->actingAs($admin)->post(route('undo.store'))->assertRedirect();
        $this->assertSame('Гарчиг 12', $decree->fresh()->title);
    }
}
