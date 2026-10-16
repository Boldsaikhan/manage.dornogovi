<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LeaveLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Чөлөө олгох лимит — журмын 5.4-р заалтыг цэснээс засна.
 */
class LeaveLimitSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LeaveLimits::forget();
    }

    public function test_the_register_carries_the_default_limits(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('limits', count(LeaveLimits::DEFAULTS))
                ->where('limits.0.code', '5.4.3')
                ->where('limits.0.period', 'quarter')
                ->where('limits.0.pay', 'none')
                ->where('limitPeriodOptions.quarter', 'Улиралд')
                ->has('rules', count(LeaveLimits::DEFAULTS))
                ->where('limitPayOptions.tsalintai', 'Цалинтай'));
    }

    public function test_an_admin_can_change_and_reset_the_limits(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('leaves.limits.update'), [
                'limits' => [
                    ['code' => '5.4.3', 'label' => 'Нэгжийн даргын олгох чөлөө', 'period' => 'quarter', 'days' => 2, 'pay' => 'tsalingui'],
                    // Нэргүй мөр хадгалагдахгүй.
                    ['code' => '5.4.4', 'label' => '  ', 'period' => 'year', 'days' => 5, 'pay' => 'tsalintai'],
                ],
            ])
            ->assertRedirect();

        LeaveLimits::forget();
        $saved = LeaveLimits::all();

        $this->assertCount(1, $saved);
        $this->assertSame('quarter', $saved[0]['period']);
        $this->assertSame(2, $saved[0]['days']);
        $this->assertSame('tsalingui', $saved[0]['pay']);
        $this->assertSame('Улиралд ажлын 2 хоног хүртэл', LeaveLimits::limitText($saved[0]));

        $this->actingAs($admin)
            ->patch(route('leaves.limits.update'), ['reset' => true])
            ->assertRedirect();

        LeaveLimits::forget();
        $this->assertSame(LeaveLimits::DEFAULTS, LeaveLimits::all());
    }

    public function test_a_viewer_cannot_change_the_limits(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->patch(route('leaves.limits.update'), ['limits' => []])
            ->assertForbidden();
    }
}
