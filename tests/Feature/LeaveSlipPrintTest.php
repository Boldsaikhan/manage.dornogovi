<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Чөлөөний хуудас — A4 босоо, нэг хуудсанд 2 хувь.
 */
class LeaveSlipPrintTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function leave(): Leave
    {
        return Leave::create([
            'user_id' => User::factory()->create()->id,
            'person_name' => 'Ц.Энхтунгалаг',
            'type' => 'tsalintai',
            'reason' => 'Хүүхдийн төрсөн өдөр',
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-20',
            'days' => 1,
        ]);
    }

    public function test_the_slip_defaults_to_two_copies_on_a4(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('leaves.slip', $this->leave()))
            ->assertOk();

        $response->assertViewHas('copies', 2);
        $response->assertViewHas('pageWidth', 210);
        $response->assertViewHas('pageHeight', 297);
    }

    public function test_the_copy_count_can_still_be_overridden(): void
    {
        $this->actingAs($this->admin())
            ->get(route('leaves.slip', $this->leave()).'?copies=6')
            ->assertOk()
            ->assertViewHas('copies', 6);
    }

    public function test_an_unknown_copy_count_falls_back_to_two(): void
    {
        $this->actingAs($this->admin())
            ->get(route('leaves.slip', $this->leave()).'?copies=3')
            ->assertOk()
            ->assertViewHas('copies', 2);
    }

    public function test_a5_and_landscape_can_be_chosen(): void
    {
        $leave = $this->leave();

        $this->actingAs($this->admin())
            ->get(route('leaves.slip', $leave).'?paper=a5')
            ->assertOk()
            ->assertViewHas('pageWidth', 148)
            ->assertViewHas('pageHeight', 210);

        $this->actingAs($this->admin())
            ->get(route('leaves.slip', $leave).'?paper=a5&orient=landscape')
            ->assertOk()
            ->assertViewHas('pageWidth', 210)
            ->assertViewHas('pageHeight', 148);
    }

    public function test_the_toolbar_offers_paper_and_orientation(): void
    {
        $this->actingAs($this->admin())
            ->get(route('leaves.slip', $this->leave()))
            ->assertOk()
            ->assertSee('Цаас:')
            ->assertSee('Чиглэл:')
            ->assertSee('Хөндлөн')
            ->assertSee('A5');
    }

    public function test_the_text_shrinks_only_when_the_slip_gets_small(): void
    {
        $leave = $this->leave();

        // Нэг A4-д нэг хувь — бүтэн хэмжээ.
        $full = $this->actingAs($this->admin())
            ->get(route('leaves.slip', $leave).'?copies=1')
            ->assertOk();

        // A5-д зургаан хувь — багтаахын тулд жижигрэнэ.
        $tight = $this->actingAs($this->admin())
            ->get(route('leaves.slip', $leave).'?paper=a5&copies=6')
            ->assertOk();

        $this->assertGreaterThan(
            $this->bodyFontPt($tight->getContent()),
            $this->bodyFontPt($full->getContent()),
        );
    }

    /* ── Дугаарлалт ──────────────────────────────────────────── */

    public function test_the_slip_number_is_handed_out_on_the_first_print(): void
    {
        $leave = $this->leave();

        $this->actingAs($this->admin())
            ->get(route('leaves.slip', $leave))
            ->assertOk()
            ->assertViewHas('slipNumber', '1');

        $this->assertSame('1', $leave->fresh()->slip_number);

        // Дахин хэвлэхэд дугаар нь өсөхгүй.
        $this->actingAs($this->admin())
            ->get(route('leaves.slip', $leave))
            ->assertOk()
            ->assertViewHas('slipNumber', '1');
    }

    public function test_each_new_slip_takes_the_next_number(): void
    {
        $first = $this->leave();
        $second = $this->leave();

        $this->actingAs($this->admin())->get(route('leaves.slip', $first))->assertOk();
        $this->actingAs($this->admin())->get(route('leaves.slip', $second))->assertOk();

        $this->assertSame('1', $first->fresh()->slip_number);
        $this->assertSame('2', $second->fresh()->slip_number);
    }

    public function test_the_numbering_starts_over_each_year(): void
    {
        $old = $this->leave();
        $old->forceFill(['start_date' => '2025-03-04', 'end_date' => '2025-03-04'])->save();

        $this->actingAs($this->admin())->get(route('leaves.slip', $old))->assertOk();
        $this->assertSame('1', $old->fresh()->slip_number);

        $this->actingAs($this->admin())->get(route('leaves.slip', $this->leave()))->assertOk();

        // 2026 он дахин 1-ээс эхэлнэ.
        $this->assertSame(2, Leave::query()->where('slip_number', '1')->count());
    }

    public function test_a_hand_written_number_is_left_alone(): void
    {
        $leave = $this->leave();
        $leave->forceFill(['slip_number' => 'А-12'])->save();

        $this->actingAs($this->admin())
            ->get(route('leaves.slip', $leave))
            ->assertOk()
            ->assertViewHas('slipNumber', 'А-12');

        // Тоон бус дугаар дарааллыг эвдэхгүй.
        $this->actingAs($this->admin())->get(route('leaves.slip', $this->leave()))->assertOk();
        $this->assertSame('1', Leave::query()->latest('id')->first()->slip_number);
    }

    /** `.slip .body` дотор бичигдсэн фонтын хэмжээ. */
    private function bodyFontPt(string $html): float
    {
        preg_match('/\.slip \.body \{[^}]*font-size:\s*([\d.]+)pt/s', $html, $m);

        return (float) ($m[1] ?? 0);
    }

}
