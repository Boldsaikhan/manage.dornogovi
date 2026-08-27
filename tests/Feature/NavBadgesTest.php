<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\TravelAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NavBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_shows_counts_for_relevant_items(): void
    {
        $user = User::factory()->create([
            'name' => 'Батбаярын Дулмаа',
            'is_admin' => true,
        ]);

        $source = TaskSource::where('key', TaskSource::KEY_DIRECTIVE)->first();
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Миний үүрэг',
            'responsible' => 'Б.Дулмаа',
            'progress' => 40,
            'sort_order' => 1,
        ]);
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Бусдын үүрэг',
            'responsible' => 'А.Болд',
            'progress' => 10,
            'sort_order' => 2,
        ]);
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Дууссан үүрэг',
            'responsible' => 'Б.Дулмаа',
            'progress' => 100,
            'sort_order' => 3,
        ]);

        Leave::create([
            'user_id' => $user->id,
            'person_name' => 'Б.Дулмаа',
            'type' => 'eeljiin',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'days' => 3,
            'status' => 'pending',
        ]);

        TravelAssignment::create([
            'user_id' => $user->id,
            'destination' => 'Улаанбаатар',
            'purpose' => 'Сургалт',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'approved',
        ]);

        $this->actingAs($user)
            ->get(route('dept.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('navBadges.tasks', 1)
                ->where('navBadges.leaves', 1)
                ->where('navBadges.assignments', 1)
                ->where('navBadges.dept_dashboard', 2)
            );
    }

    public function test_guest_gets_empty_badges(): void
    {
        $this->get(route('login'))->assertOk();
    }
}
