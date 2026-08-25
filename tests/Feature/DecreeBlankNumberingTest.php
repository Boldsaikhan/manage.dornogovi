<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecreeBlankNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_follow_previous_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Эхний мөр — 2 захирамж → 1-2
        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'blank',
            'person_name' => 'Б.Гантөмөр',
            'qty_zahiramj' => 2,
        ])->assertRedirect();

        $first = Decree::query()->firstOrFail();
        $this->assertSame('1-2', $first->num_zahiramj);

        // Дараагийн мөр — 3 захирамж (1 нь монгол бичиг) → 3-5
        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'blank',
            'person_name' => 'Ц.Батсугир',
            'qty_zahiramj' => 2,
            'qty_zahiramj_mn' => 1,
        ])->assertRedirect();

        $second = Decree::query()->latest('id')->firstOrFail();
        $this->assertSame('3-5', $second->num_zahiramj);
        $this->assertNull($second->num_tushaal);
    }

    public function test_only_one_group_is_kept(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $decree = Decree::create([
            'category' => 'blank',
            'kind' => 'blank',
            'title' => '',
            'qty_zahiramj' => 2,
            'created_by' => $admin->id,
        ]);

        // Захирамж бөглөгдсөн байхад тушаал оруулах гэвэл хүчингүй болно
        $this->actingAs($admin)
            ->patch(route('decrees.update', $decree), ['qty_tushaal' => 5])
            ->assertRedirect();

        $fresh = $decree->fresh();
        $this->assertSame(2, $fresh->qty_zahiramj);
        $this->assertSame(0, $fresh->qty_tushaal);
        $this->assertSame('1-2', $fresh->num_zahiramj);
    }

    public function test_clearing_quantity_clears_number(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $decree = Decree::create([
            'category' => 'blank',
            'kind' => 'blank',
            'title' => '',
            'qty_zahiramj' => 3,
            'num_zahiramj' => '1-3',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('decrees.update', $decree), ['qty_zahiramj' => 0])
            ->assertRedirect();

        $this->assertNull($decree->fresh()->num_zahiramj);
    }

    public function test_manual_start_number_is_respected_and_continued(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Эхлэх дугаарыг гараар тохируулна
        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'blank',
            'qty_zahiramj' => 2,
            'num_zahiramj' => '1402-1403',
        ])->assertRedirect();

        $this->assertSame('1402-1403', Decree::query()->firstOrFail()->num_zahiramj);

        // Дараагийн мөр өмнөхөөс үргэлжилнэ
        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'blank',
            'qty_zahiramj' => 3,
        ])->assertRedirect();

        $this->assertSame('1404-1406', Decree::query()->latest('id')->firstOrFail()->num_zahiramj);
    }
}
