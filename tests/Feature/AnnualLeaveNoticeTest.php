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
            ->assertSee('АЙМГИЙН ЗАСАГ ДАРГЫН ТАМГЫН ГАЗРЫН')
            ->assertSee('ДАРГЫН АЛБАН ҮҮРГИЙГ ТҮР ОРЛОН ГҮЙЦЭТГЭГЧ')
            ->assertSee('М.Мөнхбат')
            ->assertSee('Б.Чинзүрх')
            ->assertSee('Залуучуудын хөгжил, оролцоо хариуцсан ажилтан')
            ->assertSee('Залуучуудын хөгжил, оролцоо хариуцсан ажилтан Б.Чинзүрхийн', false);
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
            ->assertSee('О.Батжаргал')
            // Сонгоогүй үеийн урьдач (ЗДТГ-ын дарга М.Мөнхбат) харагдахгүй.
            ->assertDontSee('М.Мөнхбат')
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

    public function test_the_notice_text_can_be_edited_and_reset(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $row = AnnualLeave::create([
            'user_id' => $admin->id,
            'scope' => 'baiguullaga',
            'person_name' => 'Б.Чинзүрх',
        ]);

        $this->actingAs($admin)
            ->patch(route('annual-leaves.notice.text', $row), ['notice_text' => 'Гараар бичсэн өгүүлбэр.'])
            ->assertRedirect();

        $this->assertSame('Гараар бичсэн өгүүлбэр.', $row->fresh()->notice_text);

        $this->actingAs($admin)
            ->get(route('annual-leaves.notice', $row))
            ->assertOk()
            ->assertSee('Гараар бичсэн өгүүлбэр.');

        $this->actingAs($admin)
            ->patch(route('annual-leaves.notice.text', $row), ['notice_text' => ''])
            ->assertRedirect();

        $this->assertNull($row->fresh()->notice_text);
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
