<?php

namespace Tests\Feature;

use App\Models\DocumentFormat;
use App\Models\ForeignTrip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Гадаад улсад зорчих хүсэлт» — албан бичгийн стандартаар хэвлэх.
 */
class ForeignTripPrintShellTest extends TestCase
{
    use RefreshDatabase;

    private function trip(array $extra = []): ForeignTrip
    {
        return ForeignTrip::create(array_merge([
            'scope' => 'baiguullaga',
            'person_name' => 'Б.Болд',
            'position' => 'Мэргэжилтэн',
            'org_name' => 'Эрүүл мэндийн газар',
            'destination_country' => 'БНСУ',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-09',
        ], $extra));
    }

    public function test_the_sheet_follows_the_document_standard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        DocumentFormat::query()->delete();

        DocumentFormat::create([
            'key' => 'a4-alban',
            'label' => 'A4 албан бичиг',
            'width_mm' => 210,
            'height_mm' => 297,
            'margin_top_mm' => 20,
            'margin_right_mm' => 10,
            'margin_bottom_mm' => 20,
            'margin_left_mm' => 30,
            'font_name' => 'Arial',
            'font_size_pt' => 12,
            'line_spacing' => 1.5,
            'is_default' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('foreign-trips.print', $this->trip()))
            ->assertOk()
            ->assertSee('size: 210mm 297mm', false)
            ->assertSee('margin: 20mm 10mm 20mm 30mm', false)
            ->assertSee('font-family: "Arial"', false);
    }

    public function test_the_sheet_is_framed_as_a_page_with_a_print_button(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('foreign-trips.print', $this->trip()))
            ->assertOk()
            // Цаасан хуудас болж харагдана.
            ->assertSee('<div class="page">', false)
            // Хэвлэх товчтой, хэвлэхэд товч нь нуугдана.
            ->assertSee('window.print()', false)
            ->assertSee('.toolbar { display: none; }', false);
    }

    public function test_a_viewer_without_access_is_refused(): void
    {
        $stranger = User::factory()->create(['is_admin' => false]);

        $this->actingAs($stranger)
            ->get(route('foreign-trips.print', $this->trip()))
            ->assertForbidden();
    }
}
