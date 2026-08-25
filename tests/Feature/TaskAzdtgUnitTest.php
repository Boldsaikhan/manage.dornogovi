<?php

namespace Tests\Feature;

use App\Models\OrgEmployeePhone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskAzdtgUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_azdtg_person_carries_unit_as_group(): void
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
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('people.0.value', 'Ц.Мөнх-Эрдэнэ')
                ->where('people.0.category', 'azdtg')
                ->where('people.0.org', 'Төрийн захиргааны удирдлагын хэлтэс')
                // Дашбоардад бүх нэгж (үүрэггүй ч) харагдана
                ->where('azdtgUnits.0', 'Төрийн захиргааны удирдлагын хэлтэс'));
    }
}
