<?php

namespace Tests\Feature;

use App\Models\LegalAct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * «Салбарын үзүүлэлт» — суурь тоон мэдээллийн хэсэг.
 */
class LegalActIndicatorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_the_indicator_tab_has_its_own_columns(): void
    {
        $this->actingAs($this->admin())
            ->get(route('legal-acts.index', ['scope' => 'uzuulelt']))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $labels = collect($page->toArray()['props']['columns'])->pluck('label')->all();

                $this->assertContains('Үзүүлэлтийн нэр', $labels);
                $this->assertContains('Хэмжих нэгж', $labels);
                $this->assertContains('Суурь он', $labels);
                $this->assertContains('Суурь үзүүлэлт', $labels);

                // Хуулийн багана энд гарахгүй.
                $this->assertNotContains('Батлагдсан', $labels);
            });
    }

    public function test_the_law_tab_keeps_its_own_columns(): void
    {
        $this->actingAs($this->admin())
            ->get(route('legal-acts.index', ['scope' => 'huuli']))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $labels = collect($page->toArray()['props']['columns'])->pluck('label')->all();

                $this->assertContains('Актын нэр', $labels);
                $this->assertContains('Батлагдсан', $labels);
                $this->assertNotContains('Суурь он', $labels);
            });
    }

    public function test_an_indicator_row_can_be_stored(): void
    {
        $this->actingAs($this->admin())
            ->post(route('modules.store', 'legal_acts'), [
                'scope' => 'uzuulelt',
                'title' => 'Хүн амын тоо',
                'measure_unit' => 'хүн',
                'base_year' => '2025',
                'base_value' => '68 400',
                'unit_name' => 'Нийгмийн бодлогын хэлтэс',
            ])
            ->assertRedirect();

        $row = LegalAct::where('title', 'Хүн амын тоо')->firstOrFail();

        $this->assertSame('uzuulelt', $row->scope);
        $this->assertSame('хүн', $row->measure_unit);
        $this->assertSame('2025', $row->base_year);
        $this->assertSame('68 400', $row->base_value);
    }
}
