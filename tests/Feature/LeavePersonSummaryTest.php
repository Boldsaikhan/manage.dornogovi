<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\User;
use App\Support\LeaveLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Албан хаагчийн нэр дээр дарахад гарах нийт чөлөөний хураангуй.
 */
class LeavePersonSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LeaveLimits::forget();

        LeaveLimits::save([
            ['code' => 'Y', 'label' => 'Жилийн заалт', 'period' => 'year', 'days' => 5, 'pay' => 'tsalintai'],
        ], 'baiguullaga');
    }

    private function leave(array $values = []): Leave
    {
        return Leave::query()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'scope' => 'baiguullaga',
            'person_name' => 'А.Номин',
            'type' => 'tsalintai',
            'rule_code' => 'Y',
            'start_date' => now()->format('Y').'-03-02',
            'end_date' => now()->format('Y').'-03-03',
            'days' => 2,
            'status' => 'approved',
        ], $values));
    }

    public function test_the_summary_adds_up_every_leave_of_the_person(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->leave();
        $this->leave(['days' => 1, 'type' => 'tsalingui', 'rule_code' => null]);

        // Өөр хүний бүртгэл холилдохгүй.
        $this->leave(['person_name' => 'Б.Болд', 'days' => 7]);

        $data = $this->actingAs($admin)
            ->getJson(route('leaves.person', ['name' => 'А.Номин']))
            ->assertOk()
            ->json();

        $this->assertSame('А.Номин', $data['name']);
        $this->assertCount(2, $data['rows']);
        $this->assertSame(2, $data['totals']['count']);
        $this->assertSame(3, $data['totals']['days']);

        $paid = collect($data['totals']['by_type'])->firstWhere('key', 'tsalintai');
        $this->assertSame(1, $paid['count']);
        $this->assertSame(2, $paid['days']);

        // Журмын заалтын зарцуулалт.
        $this->assertSame('Y', $data['rules'][0]['code']);
        $this->assertSame(2, $data['rules'][0]['used']);
        $this->assertSame(3, $data['rules'][0]['left']);
    }

    public function test_a_viewer_without_access_is_refused(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->getJson(route('leaves.person', ['name' => 'А.Номин']))
            ->assertForbidden();
    }
}
