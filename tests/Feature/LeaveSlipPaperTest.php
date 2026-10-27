<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveSlipPaperTest extends TestCase
{
    use RefreshDatabase;

    private function leave(User $user): Leave
    {
        return Leave::create([
            'user_id' => $user->id,
            'person_name' => 'Б.Болдсайхан',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-01',
            'days' => 1,
            'type' => 'tsalintai',
            'reason' => 'Хувийн.',
        ]);
    }

    public function test_the_slip_defaults_to_a4_portrait(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('leaves.slip', $this->leave($user)))
            ->assertOk()
            ->assertSee('size: 210mm 297mm', false);
    }

    public function test_a5_landscape_is_honoured(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('leaves.slip', $this->leave($user)).'?paper=a5&orient=landscape')
            ->assertOk()
            ->assertSee('size: 210mm 148mm', false);
    }
}
