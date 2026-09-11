<?php

namespace Tests\Feature;

use App\Models\TravelAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Томилолтын маягтын ар тал — албан томилолтын үнэмлэх.
 */
class AssignmentCertificateTest extends TestCase
{
    use RefreshDatabase;

    private function assignment(array $extra = []): TravelAssignment
    {
        return TravelAssignment::create(array_merge([
            'approver' => 'chief',
            'person_name' => 'Н.Гарамжав',
            'destination' => 'Эрдэнэ сум',
            'start_date' => '2026-09-08',
            'end_date' => '2026-09-09',
            'status' => 'approved',
        ], $extra));
    }

    public function test_both_sides_are_printed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $row = $this->assignment(['certificate_text' => 'Эрдэнэ суманд 2 хоног ажиллуулахаар томилов.']);

        $this->actingAs($admin)
            ->get(route('assignments.sheet', $row))
            ->assertOk()
            // Ар тал.
            ->assertSee('Албан томилолтын', false)
            ->assertSee('Томилолтоор ажилласан тухай тэмдэглэл')
            ->assertSee('Эрдэнэ суманд 2 хоног ажиллуулахаар томилов.')
            ->assertSee('Тусгай тэмдэглэл')
            // Урд тал.
            ->assertSee('Томилолтын удирдамж')
            ->assertSee('Албан томилолтоор ажиллах төсөв');
    }

    public function test_each_side_can_be_printed_on_its_own(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('assignments.sheet', $this->assignment()))
            ->assertOk()
            // Хуудас бүр өөрийн хэвлэх товчтой.
            ->assertSee('Ар тал — албан томилолтын үнэмлэх')
            ->assertSee('Урд тал — томилолтын удирдамж')
            ->assertSee('Энэ талыг хэвлэх')
            ->assertSee('Хоёуланг хэвлэх')
            // Нөгөө талыг нуух хэв маяг.
            ->assertSee('body.print-back .sheet--front', false)
            ->assertSee('body.print-front .sheet--back', false)
            // Хуудас яг A4.
            ->assertSee('height: 297mm', false);
    }

    public function test_the_number_follows_the_register_row_number(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $first = $this->assignment();
        $second = $this->assignment();
        // Өөр хэсгийн мөр тоологдохгүй.
        $this->assignment(['approver' => 'governor']);
        $third = $this->assignment();

        foreach ([[$first, 1], [$second, 2], [$third, 3]] as [$row, $expected]) {
            $this->actingAs($admin)
                ->get(route('assignments.sheet', $row))
                ->assertOk()
                ->assertSee('Дугаар '.$expected);
        }
    }

    public function test_the_chosen_approver_signs_both_sides(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $row = $this->assignment(['approved_by' => 'М.Мөнхбат']);

        $response = $this->actingAs($admin)->get(route('assignments.sheet', $row))->assertOk();

        // Хоёр талд нь гарын үсэг зурах хүний нэр гарна.
        $this->assertSame(3, substr_count($response->getContent(), 'М.Мөнхбат'));
    }

    public function test_the_name_sits_beside_the_last_position_line(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $row = $this->assignment(['approved_by' => 'М.Мөнхбат']);

        $html = $this->actingAs($admin)
            ->get(route('assignments.sheet', $row))
            ->assertOk()
            ->getContent();

        // Албан тушаалын сүүлийн мөр, нэр хоёр нэг мөрөнд зэрэгцэнэ.
        $this->assertStringContainsString('signrow', $html);
        // «БАТЛАВ» нь голлож, мөр бүр нь тасрахгүй.
        $this->assertStringContainsString('approve__title', $html);
        $this->assertStringContainsString('approve__line', $html);
        $this->assertMatchesRegularExpression(
            '/signrow.*?ГҮЙЦЭТГЭГЧ.*?М\.Мөнхбат/su',
            $html,
        );
    }

    public function test_a_single_line_position_puts_the_name_underneath(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Засаг даргын маягтад албан тушаал нь нэг мөр.
        $row = $this->assignment([
            'approver' => 'governor',
            'approved_by' => 'О.Батжаргал',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('assignments.sheet', $row))
            ->assertOk()
            ->getContent();

        // Нэр нь албан тушаалын доор, дангаараа мөрөнд байна.
        $this->assertStringContainsString('signunder', $html);
        // Хэв маягийн тодорхойлолт үлдэнэ, харин зурагдахгүй.
        $this->assertStringNotContainsString('class="signrow"', $html);
        $this->assertMatchesRegularExpression(
            '/ДОРНОГОВЬ АЙМГИЙН ЗАСАГ ДАРГА.*?signunder.*?О\.Батжаргал/su',
            $html,
        );
    }

    public function test_the_certificate_text_is_saved_from_the_form(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->post(route('modules.store', ['module' => 'assignments']), [
                'approver' => 'chief',
                'destination' => 'Замын-Үүд',
                'start_date' => '2026-09-10',
                'end_date' => '2026-09-11',
                'certificate_text' => 'Замын-Үүд суманд 2 хоног ажиллуулахаар томилов.',
            ])
            ->assertRedirect();

        $this->assertSame(
            'Замын-Үүд суманд 2 хоног ажиллуулахаар томилов.',
            TravelAssignment::query()->latest('id')->first()->certificate_text,
        );
    }
}
