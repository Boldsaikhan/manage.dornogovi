<?php

namespace Tests\Feature;

use App\Models\AnnualLeave;
use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualLeaveNoticeTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSee('Залуучуудын хөгжил, оролцоо хариуцсан ажилтан');
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
