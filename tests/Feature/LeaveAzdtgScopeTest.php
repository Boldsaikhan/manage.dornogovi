<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\OrgEmployeePhone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LeaveAzdtgScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_azdtg_tab_lists_staff_and_accepts_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        OrgEmployeePhone::create([
            'organization' => 'Дорноговь аймгийн ЗДТГ',
            'unit' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'position' => 'Мэргэжилтэн',
            'last_name' => 'Ц',
            'first_name' => 'Мөнх-Эрдэнэ',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('leaves.index', ['scope' => 'azdtg']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Leaves')
                ->where('activeScope', 'azdtg')
                ->where('scopes.azdtg', 'АЗДТГ-ын албан хаагчид')
                ->where('tabs.2.value', 'azdtg'));

        $this->actingAs($admin)->post(route('leaves.store'), [
            'scope' => 'azdtg',
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'person_name' => 'Ц.Мөнх-Эрдэнэ',
            'signer' => 'acting',
            'type' => 'tsalintai',
            'start_date' => '2026-09-01',
            'days' => 2,
        ])->assertRedirect();

        $this->assertSame('azdtg', Leave::query()->firstOrFail()->scope);
    }
}
