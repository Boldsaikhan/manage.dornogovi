<?php

namespace Tests\Feature;

use App\Models\AnnualLeave;
use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnnualLeaveNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_notice_shows_the_registration_date(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Carbon::setTestNow('2026-09-24 10:00:00');

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Б.Чинзүрх',
        ]);

        Carbon::setTestNow();

        $this->actingAs($admin)
            ->get(route('annual-leaves.notice', $row))
            ->assertOk()
            ->assertSee('<span class="filled">2026</span> оны', false)
            ->assertSee('<span class="filled">9</span>-р сарын', false)
            ->assertSee('<span class="filled">24</span>-ны өдөр', false);
    }

    public function test_the_notice_shows_the_auto_generated_text_and_signatures(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'person_name' => 'М.Мөнхбат',
            'position' => 'ЗДТГ-ын дарга',
            'org_name' => 'АЗДТГ',
        ]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'org_name' => 'Нийгмийн бодлогын хэлтэс',
            'position' => 'Залуучуудын хөгжил, оролцоо хариуцсан ажилтан',
            'person_name' => 'Б.Чинзүрх',
            'work_years' => 3,
            'entitled_days' => 15,
            'start_date' => '2026-08-31',
            'end_date' => '2026-09-18',
        ]);

        $this->actingAs($admin)
            ->get(route('annual-leaves.notice', $row))
            ->assertOk()
            ->assertSee('Ээлжийн амралт олгох тухай мэдэгдэл')
            ->assertSee('2026 оны ээлжийн амралтыг', false)
            ->assertSee('08 дугаар сарын 31-ний өдрөөс', false)
            ->assertSee('09 дүгээр сарын 18-ны өдрийг дуустал', false)
            ->assertSee('ажлын 15 өдрөөр олгов.', false)
            ->assertSee('ЗӨВШӨӨРСӨН:')
            ->assertSee('АЙМГИЙН ЗАСАГ ДАРГЫН ТАМГЫН ГАЗРЫН')
            ->assertSee('ДАРГЫН АЛБАН ҮҮРГИЙГ ТҮР ОРЛОН ГҮЙЦЭТГЭГЧ')
            ->assertSee('М.МӨНХБАТ')
            ->assertSee('Б.Чинзүрх')
            ->assertSee('Залуучуудын хөгжил, оролцоо хариуцсан ажилтан')
            ->assertSee('Залуучуудын хөгжил, оролцоо хариуцсан ажилтан Б.Чинзүрхийн', false);
    }

    public function test_the_full_notice_combines_sentence_approver_and_own_lines_into_one_text(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'person_name' => 'О.Батжаргал',
            'position' => 'Аймгийн Засаг дарга',
            'org_name' => 'АЗДТГ',
        ]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'org_name' => 'Байгаль орчны алба',
            'position' => 'Байгаль орчны албаны дарга',
            'person_name' => 'Ш.Амарбилэг',
            'signer' => 'О.Батжаргал',
        ]);

        $text = \App\Support\AnnualLeaveNotice::fullText($row);

        $this->assertStringContainsString('ЗӨВШӨӨРСӨН:', $text);
        $this->assertStringContainsString('АЙМГИЙН ЗАСАГ ДАРГА', $text);
        $this->assertStringContainsString('О.БАТЖАРГАЛ', $text);
        $this->assertStringContainsString('БАЙГАЛЬ ОРЧНЫ АЛБАНЫ ДАРГА', $text);
        $this->assertStringContainsString('Ш.АМАРБИЛЭГ', $text);

        // Тушаал, нэрийг нэг таб тэмдэгтээр тусгаарлан хадгална (grid-ээр
        // баруун тал руугаа зэрэгцүүлж харуулна).
        $this->assertStringContainsString("АЙМГИЙН ЗАСАГ ДАРГА\tО.БАТЖАРГАЛ", $text);
        $this->assertStringContainsString("БАЙГАЛЬ ОРЧНЫ АЛБАНЫ ДАРГА\tШ.АМАРБИЛЭГ", $text);

        // Grid-д тусдаа харуулахаар тушаал, нэр тус тусдаа ялгаатай болно.
        $fields = \App\Support\AnnualLeaveNotice::signatureFields($row);
        $this->assertSame('АЙМГИЙН ЗАСАГ ДАРГА', $fields['approverTitle']);
        $this->assertSame('О.БАТЖАРГАЛ', $fields['approverName']);
        $this->assertSame('БАЙГАЛЬ ОРЧНЫ АЛБАНЫ ДАРГА', $fields['ownTitle']);
        $this->assertSame('Ш.АМАРБИЛЭГ', $fields['ownName']);
    }

    public function test_signature_fields_parse_old_format_notice_text_with_title_and_name_on_separate_lines(): void
    {
        // Grid-ээр задлах шинэчлэлээс өмнө хадгалсан бичвэрт тушаал, нэр
        // нэг мөрт tab-аар биш, тус тусдаа мөрөнд байсан — энэ хуучин
        // өгөгдлийг ч алдаагүй задлаж чадах ёстой.
        $admin = User::factory()->create(['is_admin' => true]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Ш.Амарбилэг',
            'notice_text' => "Өгүүлбэр.\n\nЗӨВШӨӨРСӨН:\nЗАСАГ ДАРГЫН ҮҮРЭГ ГҮЙЦЭТГЭГЧ\nГ.МАРТ\n\nБАЙГАЛЬ ОРЧНЫ АЛБАНЫ ДАРГА\nШ.АМАРБИЛЭГ",
        ]);

        $fields = \App\Support\AnnualLeaveNotice::signatureFields($row);

        $this->assertSame('ЗАСАГ ДАРГЫН ҮҮРЭГ ГҮЙЦЭТГЭГЧ', $fields['approverTitle']);
        $this->assertSame('Г.МАРТ', $fields['approverName']);
        $this->assertSame('БАЙГАЛЬ ОРЧНЫ АЛБАНЫ ДАРГА', $fields['ownTitle']);
        $this->assertSame('Ш.АМАРБИЛЭГ', $fields['ownName']);
    }

    public function test_the_own_signature_line_shows_the_position_exactly_as_in_the_phone_directory(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'sum',
            'org_name' => 'УЛААНБАДРАХ СУМ',
            'position' => 'Засаг дарга',
            'person_name' => 'Г.Ганбүрэн',
        ]);

        $this->actingAs($admin)
            ->get(route('annual-leaves.notice', $row))
            ->assertOk()
            ->assertSee('ЗАСАГ ДАРГА');

        $this->assertSame(
            'Засаг дарга',
            \App\Support\AnnualLeaveNotice::ownPositionLine($row->fresh()),
        );
    }

    public function test_the_own_signature_line_falls_back_when_there_is_no_position(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Б.Чинзүрх',
        ]);

        $this->assertSame('Албан хаагч', \App\Support\AnnualLeaveNotice::ownPositionLine($row->fresh()));
    }

    public function test_the_own_signature_line_does_not_repeat_the_organisation_when_the_position_already_names_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'org_name' => 'Дорноговь аймаг дахь Төрийн албаны салбар зөвлөл',
            'position' => 'Төрийн албаны салбар зөвлөлийн Дорноговь аймаг дахь салбар зөвлөлийн нарийн бичгийн даргын албан үүргийг түр орлон гүйцэтгэгч',
            'person_name' => 'Л.Оюунсүрэн',
        ]);

        $this->assertSame(
            $row->position,
            \App\Support\AnnualLeaveNotice::ownPositionLine($row->fresh()),
        );
    }

    public function test_the_notice_shows_the_registers_own_number(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $first = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Нэгдүгээр',
        ]);
        $second = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Хоёрдугаар',
        ]);
        // Өөр хамрах хүрээ — дугаарлалтад нөлөөлөхгүй.
        AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'agentlag',
            'person_name' => 'Өөр хүрээ',
        ]);

        $this->actingAs($admin)
            ->get(route('annual-leaves.notice', $first))
            ->assertOk()
            ->assertSee('Дугаар <span class="filled">1</span>', false);

        $this->actingAs($admin)
            ->get(route('annual-leaves.notice', $second))
            ->assertOk()
            ->assertSee('Дугаар <span class="filled">2</span>', false);
    }

    public function test_the_chosen_signer_appears_in_the_approved_signature(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'person_name' => 'М.Мөнхбат',
            'position' => 'ЗДТГ-ын дарга',
            'org_name' => 'АЗДТГ',
        ]);
        PhoneDirectoryEntry::create([
            'person_name' => 'О.Батжаргал',
            'position' => 'Аймгийн Засаг дарга',
            'org_name' => 'АЗДТГ',
        ]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Б.Чинзүрх',
            'signer' => 'О.Батжаргал',
        ]);

        $this->actingAs($admin)
            ->get(route('annual-leaves.notice', $row))
            ->assertOk()
            ->assertSee('АЙМГИЙН ЗАСАГ ДАРГА')
            ->assertSee('О.БАТЖАРГАЛ')
            // Сонгоогүй үеийн урьдач (ЗДТГ-ын дарга М.Мөнхбат) харагдахгүй.
            ->assertDontSee('М.МӨНХБАТ')
            ->assertDontSee('ДАРГЫН АЛБАН ҮҮРГИЙГ ТҮР ОРЛОН ГҮЙЦЭТГЭГЧ');
    }

    public function test_the_body_text_does_not_repeat_the_organisation_when_the_position_already_names_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            // Тушаалын бичвэрт байгууллагын нэр аль хэдийн орсон байдаг.
            'org_name' => 'Дорноговь аймаг дахь Төрийн албаны салбар зөвлөл',
            'position' => 'Төрийн албаны салбар зөвлөлийн Дорноговь аймаг дахь салбар зөвлөлийн нарийн бичгийн даргын албан үүргийг түр орлон гүйцэтгэгч',
            'person_name' => 'Л.Оюунсүрэн',
            'entitled_days' => 15,
            'start_date' => '2026-09-22',
            'end_date' => '2026-10-06',
        ]);

        $text = \App\Support\AnnualLeaveNotice::text($row);

        $this->assertSame(1, substr_count($text, 'Төрийн албаны салбар зөвлөлийн'));
        $this->assertSame(1, substr_count($text, 'Дорноговь аймаг дахь'));
        $this->assertStringStartsWith('Төрийн албаны салбар зөвлөлийн Дорноговь аймаг дахь', $text);
    }

    public function test_the_body_text_names_the_organisation_when_the_short_position_does_not_already_name_it(): void
    {
        // «Засаг дарга» мэт богино тушаал сумын нэрийг агуулдаггүй тул
        // (өмнөх тохиолдлоос ялгаатайгаар) байгууллагын (сумын) нэрийг
        // урдаа нэмж залгах ёстой — үгүй бол ямар сумын дарга болохыг
        // мэдэгдэлд огт дурдахгүй үлдэнэ.
        $admin = User::factory()->create(['is_admin' => true]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'sum',
            'org_name' => 'Замын-Үүд сум',
            'position' => 'Засаг дарга',
            'person_name' => 'Б.Сайнбаяр',
            'entitled_days' => 20,
            'start_date' => '2026-10-12',
            'end_date' => '2026-11-06',
        ]);

        $text = \App\Support\AnnualLeaveNotice::text($row);

        $this->assertStringStartsWith('Замын-Үүд сумын Засаг дарга Б.Сайнбаярын', $text);
    }

    public function test_an_all_caps_org_name_is_lowered_and_joined_without_a_hyphen(): void
    {
        // Байгууллагын нэрийг бүхэлд нь ТОМ ҮСГЭЭР хадгалсан бол
        // MongolianCase::genitiveWord() үүнийг товчлол (ЗДТГ мэт) гэж
        // андуураад «СУМ-ын» гэж зурааст холбоос үүсгэдэг байв. Мөн
        // бичвэр бүхэлдээ ТОМ ҮСГЭЭР харагддаг байсан. Одоо жижиг
        // үсэг рүү буулгаж, зурааст холбоосгүйгээр шууд залгана.
        $admin = User::factory()->create(['is_admin' => true]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'sum',
            'org_name' => 'ЗАМЫН-ҮҮД СУМ',
            'position' => 'Засаг дарга',
            'person_name' => 'Б.Сайнбаяр',
            'entitled_days' => 20,
            'start_date' => '2026-10-12',
            'end_date' => '2026-11-06',
        ]);

        $text = \App\Support\AnnualLeaveNotice::text($row);

        $this->assertStringStartsWith('Замын-Үүд сумын Засаг дарга Б.Сайнбаярын', $text);
        $this->assertStringNotContainsString('СУМ-ын', $text);
        $this->assertStringNotContainsString('ЗАМЫН-ҮҮД', $text);
    }

    public function test_the_whole_notice_can_be_edited_in_one_field_and_reset(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'person_name' => 'О.Батжаргал',
            'position' => 'Аймгийн Засаг дарга',
            'org_name' => 'АЗДТГ',
        ]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'org_name' => 'Байгаль орчны алба',
            'position' => 'Байгаль орчны албаны дарга',
            'person_name' => 'Ш.Амарбилэг',
            'signer' => 'О.Батжаргал',
        ]);

        $this->actingAs($admin)
            ->get(route('annual-leaves.notice', $row))
            ->assertOk()
            ->assertSee('АЙМГИЙН ЗАСАГ ДАРГА')
            ->assertSee('О.БАТЖАРГАЛ')
            ->assertSee('БАЙГАЛЬ ОРЧНЫ АЛБАНЫ ДАРГА')
            ->assertSee('Ш.АМАРБИЛЭГ');

        $full = "Гараар бичсэн бүхэл бичвэр.\n\nЗӨВШӨӨРСӨН:\nГАРААР ЗАСВАРЛАСАН ТУШААЛ\nХ.Хэн нэгэн";

        $this->actingAs($admin)
            ->patch(route('annual-leaves.notice.text', $row), ['notice_text' => $full])
            ->assertRedirect();

        $this->assertSame($full, $row->fresh()->notice_text);
        $this->assertSame($full, \App\Support\AnnualLeaveNotice::fullText($row->fresh()));

        $this->actingAs($admin)
            ->get(route('annual-leaves.notice', $row))
            ->assertOk()
            ->assertSee('Гараар бичсэн бүхэл бичвэр.')
            ->assertSee('ГАРААР ЗАСВАРЛАСАН ТУШААЛ')
            ->assertSee('Х.Хэн нэгэн')
            ->assertDontSee('АЙМГИЙН ЗАСАГ ДАРГА')
            ->assertDontSee('О.БАТЖАРГАЛ');

        $this->actingAs($admin)
            ->patch(route('annual-leaves.notice.text', $row), ['notice_text' => ''])
            ->assertRedirect();

        $this->assertNull($row->fresh()->notice_text);

        $this->actingAs($admin)
            ->get(route('annual-leaves.notice', $row))
            ->assertOk()
            ->assertSee('АЙМГИЙН ЗАСАГ ДАРГА')
            ->assertSee('О.БАТЖАРГАЛ');
    }

    public function test_the_approver_name_is_shown_in_uppercase(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'person_name' => 'Ш.Амарбилэг',
            'position' => 'Байгаль орчны албаны дарга',
            'org_name' => 'Байгаль орчны алба',
        ]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Б.Чинзүрх',
            'signer' => 'Ш.Амарбилэг',
        ]);

        $this->assertSame('Ш.АМАРБИЛЭГ', \App\Support\AnnualLeaveNotice::approverName($row));
    }

    public function test_a_viewer_without_edit_access_cannot_change_the_text(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $viewer = User::factory()->create();

        \App\Models\UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => 'annual_leaves',
            'level' => 'view',
        ]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Б.Чинзүрх',
        ]);

        $this->actingAs($viewer)
            ->get(route('annual-leaves.notice', $row))
            ->assertOk();

        $this->actingAs($viewer)
            ->patch(route('annual-leaves.notice.text', $row), ['notice_text' => 'Оролдлого'])
            ->assertForbidden();
    }
}
