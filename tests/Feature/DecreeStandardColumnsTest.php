<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DecreeStandardColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_zahiramj_row_carries_standard_columns(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => 'A/123',
            'title' => 'Тест захирамж',
            'blank_number' => '810',
            'issued_on' => '2026-08-25',
            'person_name' => 'Б.Батбаяр',
            'qty' => 5,
            'qty_mn' => 2,
            'sheet_number' => '810-814',
            'void_number' => '811',
        ])->assertRedirect();

        $decree = Decree::query()->firstOrFail();
        $this->assertSame('zahiramj', $decree->category);
        $this->assertSame(5, $decree->qty_zahiramj);
        $this->assertSame(2, $decree->qty_zahiramj_mn);
        $this->assertSame(0, $decree->qty_tushaal);
        $this->assertSame('810-814', $decree->num_zahiramj);
        $this->assertNull($decree->num_tushaal);
        $this->assertSame('811', $decree->void_zahiramj);

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'zahiramj']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Decrees')
                ->where('rows.0.person_name', 'Б.Батбаяр')
                ->where('rows.0.qty_zahiramj', 5)
                ->where('rows.0.num_zahiramj', '810-814')
                ->where('rows.0.void_zahiramj', '811'));
    }
}
