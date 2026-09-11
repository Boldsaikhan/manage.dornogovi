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

    public function test_the_sentence_is_built_when_nothing_was_typed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Намсрайн Гарамжав',
            'position' => 'Эрчим хүчний хяналтын улсын байцаагч',
            'org_name' => 'СХЗХ',
        ]);

        // Бичвэрийг гараар бичээгүй мөр.
        $row = $this->assignment([
            'person_name' => 'Н.Гарамжав',
            'destination' => 'Эрдэнэ сум',
            'purpose' => 'албан ажил',
            'start_date' => '2026-09-08',
            'end_date' => '2026-09-09',
            'certificate_text' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('assignments.sheet', $row))
            ->assertOk()
            ->assertSee('СХЗХ-ын Эрчим хүчний хяналтын улсын байцаагч Н.Гарамжав Эрдэнэ сум '
                .'албан ажил-аар 2026 оны 9 дүгээр сарын 8-ны өдрөөс 2 хоног ажиллуулахаар томилов.');
    }

    public function test_a_typed_sentence_is_kept(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $row = $this->assignment(['certificate_text' => 'Гараар бичсэн бичвэр.']);

        $this->actingAs($admin)
            ->get(route('assignments.sheet', $row))
            ->assertOk()
            ->assertSee('Гараар бичсэн бичвэр.');
    }

    public function test_the_form_offers_the_directory_staff(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'Н.Гарамжав',
            'position' => 'Эрчим хүчний хяналтын улсын байцаагч',
            'org_name' => 'СХЗХ',
        ]);

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertOk()
            ->assertInertia(function (\Inertia\Testing\AssertableInertia $page) {
                $people = $page->toArray()['props']['formMeta']['people'];
                $row = collect($people)->firstWhere('value', 'Н.Гарамжав');

                // Үүрэг даалгаварын сонгогчтой ижил бүтэц.
                $this->assertSame('Эрчим хүчний хяналтын улсын байцаагч', $row['hint']);
                $this->assertSame('СХЗХ', $row['org']);
                $this->assertArrayHasKey('category', $row);
            });
    }

    public function test_the_chosen_staff_name_and_position_are_stored(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->from(route('assignments.index', ['scope' => 'chief']))
            ->post(route('modules.store', ['module' => 'assignments']), [
                'approver' => 'chief',
                'person_name' => 'Н.Гарамжав',
                'position' => 'Эрчим хүчний хяналтын улсын байцаагч',
                'destination' => 'Эрдэнэ сум',
                'start_date' => '2026-09-08',
                'end_date' => '2026-09-09',
            ])
            ->assertRedirect();

        $row = TravelAssignment::query()->latest('id')->first();

        $this->assertSame('Н.Гарамжав', $row->person_name);
        $this->assertSame('Эрчим хүчний хяналтын улсын байцаагч', $row->position);

        // Бүртгэлийн хүснэгтэд ч тэр нэрээр харагдана.
        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('rows.0.user_name', 'Н.Гарамжав')
                ->where('rows.0.user_position', 'Эрчим хүчний хяналтын улсын байцаагч'));
    }

    public function test_the_position_follows_the_chosen_approver(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach ([
            ['О.Батжаргал', 'Аймгийн Засаг дарга'],
            ['Г.Март', 'Засаг даргын орлогч'],
        ] as [$name, $position]) {
            \App\Models\PhoneDirectoryEntry::create([
                'person_name' => $name,
                'position' => $position,
                'org_name' => 'Аймгийн удирдлага',
                'category' => 'udirdlaga',
            ]);
        }

        $row = $this->assignment([
            'approver' => 'governor',
            'approved_by' => 'Г.Март',
        ]);

        $this->actingAs($admin)
            ->get(route('assignments.sheet', $row))
            ->assertOk()
            // Орлогчийг сонгоход албан тушаал нь дагаж солигдоно.
            ->assertSee('ДОРНОГОВЬ АЙМГИЙН ЗАСАГ ДАРГЫН ОРЛОГЧ')
            ->assertDontSee('ДОРНОГОВЬ АЙМГИЙН ЗАСАГ ДАРГА<');
    }

    public function test_the_default_approver_keeps_the_paper_wording(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'person_name' => 'М.Мөнхбат',
            'position' => 'ЗДТГ-ын дарга',
            'org_name' => 'Аймгийн удирдлага',
            'category' => 'udirdlaga',
        ]);

        $row = $this->assignment(['approver' => 'chief', 'approved_by' => 'М.Мөнхбат']);

        $this->actingAs($admin)
            ->get(route('assignments.sheet', $row))
            ->assertOk()
            // Үндсэн батлагчид цаасан маягтын бичвэр хэвээр.
            ->assertSee('ДАРГЫН АЛБАН ҮҮРГИЙГ ТҮР ОРЛОН');
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
