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
}
