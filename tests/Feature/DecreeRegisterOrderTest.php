<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Бүртгэл нь дугаараараа эрэмблэгдэж, шинэ мөр дээд талд гарна.
 */
class DecreeRegisterOrderTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $number, ?string $issuedOn = null): Decree
    {
        return Decree::create([
            'category' => 'tushaal',
            'kind' => 'tushaal_a',
            'number' => $number,
            'title' => 'Тушаал '.$number,
            'issued_on' => $issuedOn,
        ]);
    }

    public function test_rows_are_listed_from_the_largest_number_down(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Оруулах дараалал нь дугаарын дараалалтай таарахгүй.
        $this->row('07');
        $this->row('01');
        $this->row('10');
        $this->row('02');

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'tushaal_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.number', '10')
                ->where('rows.1.number', '07')
                ->where('rows.2.number', '02')
                ->where('rows.3.number', '01')
                // Д/д нь дугаарын дарааллаар өгөгдөнө: А/01 → 1, А/10 → 4.
                ->where('rows.0.no', 4)
                ->where('rows.3.no', 1));
    }

    public function test_a_new_row_appears_at_the_top(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->row('01');
        $this->row('02');

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'tushaal_a',
            'title' => 'Шинэ мөр',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'tushaal_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.number', '03')
                ->where('rows.0.title', 'Шинэ мөр'));
    }

    public function test_the_blank_tab_also_shows_the_newest_first(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['Нэгдүгээр', 'Хоёрдугаар', 'Гуравдугаар'] as $name) {
            Decree::create([
                'category' => 'blank',
                'kind' => 'blank',
                'person_name' => $name,
                'title' => $name,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'blank']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.person_name', 'Гуравдугаар')
                ->where('rows.2.person_name', 'Нэгдүгээр')
                // Д/д нь бүртгэлийн дарааллаар үлдэнэ.
                ->where('rows.0.no', 3)
                ->where('rows.2.no', 1));
    }

    public function test_the_printed_register_stays_in_ascending_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->row('02', '2026-02-02');
        $this->row('01', '2026-01-01');

        $html = $this->actingAs($admin)
            ->get(route('decrees.print', ['tab' => 'tushaal_a']))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'Тушаал 02'),
            strpos($html, 'Тушаал 01'),
            'Хэвлэх хуудсанд бага дугаар нь эхэнд байх ёстой.',
        );
    }
}
