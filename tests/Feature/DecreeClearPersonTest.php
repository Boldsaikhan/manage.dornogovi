<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecreeClearPersonTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_name_can_be_cleared(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $decree = Decree::create([
            'category' => 'blank',
            'kind' => 'blank',
            'person_name' => 'Б.Гантөмөр',
            'title' => 'Б.Гантөмөр',
            'issued_on' => '2026-08-25',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('decrees.update', $decree), ['person_name' => ''])
            ->assertRedirect();

        $this->assertNull($decree->fresh()->person_name);
    }
}
