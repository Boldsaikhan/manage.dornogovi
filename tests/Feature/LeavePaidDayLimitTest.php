<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\User;
use App\Support\LeaveLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Жилийн нийлбэр цалинтай чөлөөний хязгаар.
 *
 * Хоног нь журмын заалт тус бүрдээ бодогддог болсон тул энэ нийлбэр
 * хязгаар анхдагчаар унтраалттай. Тохируулбал ажилласан хэвээр байхыг
 * энд шалгана.
 */
class LeavePaidDayLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LeaveLimits::forget();

        // Нийлбэр хязгаарыг зориуд асаана (анхдагчаар унтраалттай).
        LeaveLimits::savePaidDaysPerYear(5);
    }

    public function test_the_overall_cap_is_off_by_default(): void
    {
        LeaveLimits::reset();

        $this->assertSame(0, LeaveLimits::paidDaysPerYear());

        $admin = $this->admin();
        $leave = $this->addLeave($admin, '2026-02-02', 40);

        // Заалт тус бүрийн лимит л үйлчилнэ — нийлбэр хязгаар байхгүй.
        $this->assertSame('tsalintai', $leave->fresh()->type);
        $this->assertFalse((bool) $leave->fresh()->limit_exceeded);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function addLeave(User $admin, string $start, int $days): Leave
    {
        $this->actingAs($admin)
            ->post(route('leaves.store'), [
                'scope' => 'baiguullaga',
                'person_name' => 'А.Номин',
                'type' => 'tsalintai',
                'start_date' => $start,
                'days' => $days,
            ])
            ->assertRedirect();

        return Leave::query()->latest('id')->firstOrFail();
    }

    public function test_a_new_blank_row_is_empty(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('leaves.store'), ['scope' => 'baiguullaga'])
            ->assertRedirect();

        $leave = Leave::query()->sole();

        $this->assertNull($leave->person_name);

        $this->actingAs($admin)
            ->get(route('leaves.index'))
            ->assertOk()
            // Бүртгэл хөтлөгчийн нэр мөрөнд орж ирэхгүй.
            ->assertInertia(fn ($page) => $page->where('rows.0.person_name', ''));
    }

    public function test_choosing_the_officer_fills_the_organisation_and_the_unit(): void
    {
        $admin = $this->admin();

        \App\Models\PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'person_name' => 'А.Номин',
            'position' => 'Мэргэжилтэн',
        ]);

        \App\Models\PhoneDirectoryEntry::create([
            'org_name' => 'Эрүүл мэндийн газар',
            'person_name' => 'Б.Болд',
            'position' => 'Дарга',
        ]);

        $this->actingAs($admin)
            ->post(route('leaves.store'), ['scope' => 'baiguullaga'])
            ->assertRedirect();

        $leave = Leave::query()->sole();

        // Хэлтсийн ажилтан — толгой байгууллага нь нөхөгдөнө.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['person_name' => 'А.Номин'])
            ->assertRedirect();

        $fresh = $leave->fresh();
        $this->assertSame(\App\Models\PhoneDirectoryEntry::PARENT_ORG, $fresh->org_name);
        $this->assertSame('Төрийн захиргааны удирдлагын хэлтэс', $fresh->unit_name);

        // «Хэлтэс» гэсэн үггүй ч ангилал нь нэгж бол толгой байгууллага нөхөгдөнө.
        \App\Models\PhoneDirectoryEntry::create([
            'org_name' => 'Хэвлэл мэдээллийн алба',
            'category' => 'heltes',
            'person_name' => 'Л.Хатанбаатар',
            'position' => 'Дарга',
        ]);

        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['person_name' => 'Л.Хатанбаатар'])
            ->assertRedirect();

        $fresh = $leave->fresh();
        $this->assertSame(\App\Models\PhoneDirectoryEntry::PARENT_ORG, $fresh->org_name);
        $this->assertSame('Хэвлэл мэдээллийн алба', $fresh->unit_name);

        // Агентлагийн ажилтан — хэлтэс хоосон.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['person_name' => 'Б.Болд'])
            ->assertRedirect();

        $fresh = $leave->fresh();
        $this->assertSame('Эрүүл мэндийн газар', $fresh->org_name);
        $this->assertNull($fresh->unit_name);

        // Нэрийг арилгахад хоёулаа цэвэрлэгдэнэ.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['person_name' => ''])
            ->assertRedirect();

        $fresh = $leave->fresh();
        $this->assertNull($fresh->org_name);
        $this->assertNull($fresh->unit_name);
    }

    public function test_leave_over_the_yearly_cap_becomes_unpaid(): void
    {
        $admin = $this->admin();

        // Лимит 5 хоног — эхний 4 хоног цалинтай хэвээр.
        $first = $this->addLeave($admin, '2026-02-02', 4);
        $this->assertSame('tsalintai', $first->fresh()->type);
        $this->assertFalse($first->fresh()->limit_exceeded);

        // 4 + 3 = 7 > 5 тул хоёр дахь нь цалингүй.
        $second = $this->addLeave($admin, '2026-05-04', 3);
        $this->assertSame('tsalingui', $second->fresh()->type);
        $this->assertTrue($second->fresh()->limit_exceeded);

        // Эхнийх нь хөндөгдөөгүй.
        $this->assertSame('tsalintai', $first->fresh()->type);

        // Дараа жил дахин шинээр тооцогдоно.
        $next = $this->addLeave($admin, '2027-01-05', 5);
        $this->assertSame('tsalintai', $next->fresh()->type);
    }

    public function test_annual_leave_stays_available_when_the_cap_is_used_up(): void
    {
        $admin = $this->admin();

        $this->addLeave($admin, '2026-02-02', 5);
        $over = $this->addLeave($admin, '2026-03-02', 2);

        $this->assertTrue($over->fresh()->limit_exceeded);

        // «Ээлжийн амралтаас» сонгоход саадгүй.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $over), ['type' => 'eeljiin'])
            ->assertSessionHasNoErrors();

        $this->assertSame('eeljiin', $over->fresh()->type);
    }

    public function test_the_type_cannot_be_changed_back_to_paid(): void
    {
        $admin = $this->admin();

        $this->addLeave($admin, '2026-02-02', 5);
        $over = $this->addLeave($admin, '2026-03-02', 2);

        $this->assertTrue($over->fresh()->limit_exceeded);

        // Сервер «Цалинтай» болгохыг хүлээж авалгүй цалингүй хэвээр үлдээнэ.
        $this->actingAs($admin)
            ->patch(route('leaves.update', $over), ['type' => 'tsalintai'])
            ->assertRedirect();

        $this->assertSame('tsalingui', $over->fresh()->type);
    }

    public function test_deleting_an_earlier_leave_frees_the_cap(): void
    {
        $admin = $this->admin();

        $first = $this->addLeave($admin, '2026-02-02', 5);
        $second = $this->addLeave($admin, '2026-03-02', 2);

        $this->assertTrue($second->fresh()->limit_exceeded);

        $this->actingAs($admin)
            ->delete(route('leaves.destroy', $first))
            ->assertRedirect();

        $this->assertSame('tsalintai', $second->fresh()->type);
        $this->assertFalse($second->fresh()->limit_exceeded);
    }

    public function test_a_zero_cap_means_no_limit(): void
    {
        LeaveLimits::savePaidDaysPerYear(0);

        $admin = $this->admin();

        $leave = $this->addLeave($admin, '2026-02-02', 60);

        $this->assertSame('tsalintai', $leave->fresh()->type);
        $this->assertFalse($leave->fresh()->limit_exceeded);
    }

    public function test_changing_the_cap_recalculates_every_record(): void
    {
        $admin = $this->admin();

        $first = $this->addLeave($admin, '2026-02-02', 3);
        $second = $this->addLeave($admin, '2026-03-02', 2);

        $this->assertSame('tsalintai', $second->fresh()->type);

        // Хязгаарыг багасгахад бүх бүртгэл дахин тооцогдоно.
        LeaveLimits::savePaidDaysPerYear(3);

        $this->actingAs($admin)
            ->patch(route('leaves.limits.update'), ['limits' => LeaveLimits::DEFAULTS])
            ->assertRedirect();

        $this->assertSame('tsalintai', $first->fresh()->type);
        $this->assertSame('tsalingui', $second->fresh()->type);
    }
}
