<?php

namespace Tests\Feature;

use App\Models\TravelAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TravelAssignmentSheetTest extends TestCase
{
    use RefreshDatabase;

    private function assignment(string $approver = 'governor'): TravelAssignment
    {
        return TravelAssignment::create([
            'user_id' => User::factory()->create(['name' => 'Б.Болд'])->id,
            'approver' => $approver,
            'destination' => 'Улаанбаатар',
            'purpose' => 'Сургалтад оролцох',
            'composition' => 'Б.Болд, Д.Дорж',
            'scope_of_work' => 'Яамтай уулзаж, төслийн явцыг танилцуулах',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'status' => 'approved',
        ]);
    }

    public function test_the_sheet_shows_the_form_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $assignment = $this->assignment();

        $this->actingAs($admin)
            ->get(route('assignments.sheet', $assignment))
            ->assertOk()
            ->assertSee('Томилолтын удирдамж', false)
            ->assertSee('Сургалтад оролцох')
            ->assertSee('Б.Болд, Д.Дорж')
            ->assertSee('Яамтай уулзаж, төслийн явцыг танилцуулах')
            ->assertSee('2026.09.10 — 2026.09.12', false)
            // Төсвийн хүснэгт нь гараар бөглөхөөр хоосон гарна.
            ->assertSee('Албан томилолтоор ажиллах төсөв')
            ->assertSee('Түлш, шатахуун')
            ->assertSee('Томилолтын тайлан');
    }

    public function test_each_approver_gets_its_own_header(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('assignments.sheet', $this->assignment('governor')))
            ->assertSee('ДОРНОГОВЬ АЙМГИЙН ЗАСАГ ДАРГА');

        $this->actingAs($admin)
            ->get(route('assignments.sheet', $this->assignment('chief')))
            ->assertSee('ДОРНОГОВЬ АЙМГИЙН ЗДТГ-ЫН');
    }

    public function test_the_register_is_split_by_approver(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assignment('governor');
        $this->assignment('chief');

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
                ->where('activeScope', 'chief')
            );

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'governor']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1));
    }

    public function test_the_new_record_form_uses_the_a4_sheet_layout(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('assignments.index', ['scope' => 'chief']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('formLayout', 'assignment_sheet')
                ->where('formMeta.lines.0', 'ДОРНОГОВЬ АЙМГИЙН ЗДТГ-ЫН')
                ->has('formMeta.budget_kinds', 3)
            );
    }

    public function test_a_user_without_access_cannot_print(): void
    {
        $user = User::factory()->create();
        $assignment = $this->assignment();

        \App\Models\UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'assignments',
            'level' => 'view',
        ]);

        // Эрхтэй хүн харна.
        $this->actingAs($user)->get(route('assignments.sheet', $assignment))->assertOk();

        $other = User::factory()->create();
        \App\Models\UserModulePermission::where('user_id', $other->id)->delete();

        $this->actingAs($other)->get(route('assignments.sheet', $assignment))->assertForbidden();
    }
}
