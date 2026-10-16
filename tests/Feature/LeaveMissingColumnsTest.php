<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LeaveBalance;
use App\Support\LeaveLimits;
use App\Support\LeaveSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migration ажиллаж амжаагүй сервер дээр ч цэс нээгдэх ёстой.
 *
 * Deploy нь кодыг эхлээд хуулдаг тул шинэ багана үүсэхээс өмнө хуудас
 * нээгдэх тохиолдол гардаг — тэр үед 500 алдаа өгөхгүй, зөвхөн шинэ
 * боломж нь түр идэвхгүй байна.
 */
class LeaveMissingColumnsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LeaveLimits::forget();

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn(['rule_code', 'unit_name', 'limit_exceeded']);
        });

        LeaveSchema::forget();
        LeaveBalance::forget();
    }

    protected function tearDown(): void
    {
        LeaveSchema::forget();
        LeaveBalance::forget();

        parent::tearDown();
    }

    public function test_the_register_still_opens(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('rules', 0));
    }

    public function test_a_row_can_still_be_added_and_filled(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('leaves.store'), ['scope' => 'baiguullaga'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $leave = \App\Models\Leave::query()->sole();

        $this->actingAs($admin)
            ->patch(route('leaves.update', $leave), ['person_name' => 'А.Номин'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('А.Номин', $leave->fresh()->person_name);
    }
}
