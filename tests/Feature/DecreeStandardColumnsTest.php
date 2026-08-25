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

    public function test_blank_number_tab_stores_printed_form_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'blank',
            'person_name' => 'Б.Батбаяр',
            'issued_on' => '2026-08-25',
            'qty_zahiramj' => 5,
            'qty_zahiramj_mn' => 2,
            'num_zahiramj' => '810-814',
            'void_zahiramj' => '811',
        ])->assertRedirect(route('decrees.index', ['tab' => 'blank']));

        $decree = Decree::query()->firstOrFail();
        $this->assertSame('blank', $decree->category);
        $this->assertSame(5, $decree->qty_zahiramj);
        $this->assertSame('810-814', $decree->num_zahiramj);

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'blank']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Decrees')
                ->where('tab', 'blank')
                ->where('rows.0.person_name', 'Б.Батбаяр')
                ->where('rows.0.num_zahiramj', '810-814'));
    }

    public function test_blank_row_can_be_created_empty_and_patched_inline(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'blank',
        ])->assertRedirect(route('decrees.index', ['tab' => 'blank']));

        $decree = Decree::query()->firstOrFail();
        $this->assertSame('blank', $decree->category);

        $this->actingAs($admin)->patch(route('decrees.update', $decree), [
            'person_name' => 'А.Ариунболд',
            'qty_zahiramj' => 3,
            'num_zahiramj' => '100-102',
        ])->assertRedirect();

        $decree->refresh();
        $this->assertSame('А.Ариунболд', $decree->person_name);
        $this->assertSame(3, $decree->qty_zahiramj);
        $this->assertSame('100-102', $decree->num_zahiramj);
        $this->assertSame('100-102', $decree->blank_number);
    }

    public function test_zahiramj_number_tab_stores_register_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => '2026 оныг бүтээмжийн жил болгон зарлах тухай',
            'issued_on' => '2026-01-02',
            'page_count' => 2,
            'attachment_name' => 'Арын бичилт',
            'attachment_pages' => 1,
            'person_name' => 'Б.Зоригтбаатар',
        ])->assertRedirect(route('decrees.index', ['tab' => 'zahiramj']));

        $decree = Decree::query()->firstOrFail();
        $this->assertSame('zahiramj', $decree->category);
        $this->assertSame('01', $decree->number);
        $this->assertSame(2, $decree->page_count);
        $this->assertSame('Арын бичилт', $decree->attachment_name);
        $this->assertSame(1, $decree->attachment_pages);
        $this->assertSame('Б.Зоригтбаатар', $decree->person_name);

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'zahiramj']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Decrees')
                ->where('tab', 'zahiramj')
                ->where('rows.0.number', '01')
                ->where('rows.0.page_count', 2)
                ->where('rows.0.attachment_name', 'Арын бичилт'));
    }
}
