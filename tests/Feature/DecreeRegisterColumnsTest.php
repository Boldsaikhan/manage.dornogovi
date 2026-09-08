<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Захирамж/тушаалын бүртгэл нь албан ёсны маягтын багануудтай эсэх.
 */
class DecreeRegisterColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function decree(string $tab = 'zahiramj_a'): Decree
    {
        return Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Ажлын хэсэг байгуулах тухай',
            'issued_on' => '2026-09-01',
            'page_count' => 2,
            'effective_on' => '2026-09-05',
            'attachment_name' => 'Ажлын хэсгийн бүрэлдэхүүн',
            'attachment_pages' => 1,
            'original_form' => 'Эх хувь',
            'file_index' => '01-04',
            'person_name' => 'Б.Болд',
        ]);
    }

    public function test_new_official_fields_are_saved_and_returned(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->decree();

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.effective_on', '2026-09-05')
                ->where('rows.0.original_form', 'Эх хувь')
                ->where('rows.0.file_index', '01-04')
            );
    }

    public function test_a_row_can_be_updated_with_the_new_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $decree = $this->decree();

        $this->actingAs($admin)
            ->patch(route('decrees.update', $decree), [
                'effective_on' => '2026-10-01',
                'original_form' => 'Хуулбар',
                'file_index' => '02-11',
            ])
            ->assertSessionHasNoErrors();

        $decree->refresh();

        $this->assertSame('2026-10-01', $decree->effective_on->format('Y-m-d'));
        $this->assertSame('Хуулбар', $decree->original_form);
        $this->assertSame('02-11', $decree->file_index);
    }

    public function test_the_number_is_shown_with_the_kind_prefix(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->decree();

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Санд зөвхөн дугаар, харуулахдаа угтвартай.
                ->where('rows.0.number', '01')
                ->where('rows.0.number_prefix', 'А')
                ->where('rows.0.number_display', 'А/01')
            );
    }

    public function test_the_b_kind_uses_its_own_prefix(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_b',
            'number' => '07',
            'title' => 'Б төрлийн захирамж',
            'issued_on' => '2026-09-02',
        ]);

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'zahiramj_b']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.number_display', 'Б/07')
            );
    }

    public function test_the_printed_page_uses_the_prefixed_number(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->decree();

        $this->actingAs($admin)
            ->get(route('decrees.print', ['tab' => 'zahiramj_a']))
            ->assertOk()
            ->assertSee('А/01');
    }

    public function test_effective_date_defaults_to_the_approved_date(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $decree = Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '05',
            'title' => 'Дагаж мөрдөх огноогүй',
            'issued_on' => '2026-09-10',
        ]);

        $this->assertNull($decree->effective_on);

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Санд хоосон, харагдахдаа батлагдсан огноогоо дагана.
                ->where('rows.0.effective_on', null)
                ->where('rows.0.effective_on_display', '2026-09-10')
            );
    }

    public function test_an_explicit_effective_date_wins(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->decree();

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.effective_on_display', '2026-09-05')
            );
    }

    public function test_print_page_shows_the_official_headings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->decree();

        $this->actingAs($admin)
            ->get(route('decrees.print', ['tab' => 'zahiramj_a']))
            ->assertOk()
            ->assertSee('Захирамжлалын баримт бичгийн үндсэн мэдээлэл')
            ->assertSee('Батлагдсан огноо')
            ->assertSee('Бүртгэлийн дугаар')
            ->assertSee('Дагаж мөрдөх', false)
            ->assertSee('эх хувийн шинж', false)
            ->assertSee('хэргийн индекс', false);
    }

    public function test_excel_export_carries_the_new_columns(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->decree();

        $this->actingAs($admin)
            ->get(route('decrees.export', ['tab' => 'zahiramj_a', 'format' => 'xlsx']))
            ->assertOk();
    }
}
