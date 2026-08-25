<?php

namespace Tests\Feature;

use App\Models\OrgEmployeePhone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OrgEmployeePhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_tab_and_store(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('phone-directory.index', ['tab' => 'staff']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/PhoneDirectory')
                ->where('tab', 'staff')
                ->has('staff', 0)
            );

        $this->actingAs($admin)
            ->post(route('phone-directory.staff.store'), [
                'organization' => 'ЗДТГ',
                'unit' => 'Хүний нөөц',
                'position' => 'Мэргэжилтэн',
                'last_name' => 'Бат',
                'first_name' => 'Болд',
                'room' => '201',
                'work_phone' => '7052-1234',
                'mobile_phone' => '99112233',
                'email' => 'bold@example.com',
            ])
            ->assertRedirect(route('phone-directory.index', ['tab' => 'staff']));

        $this->assertDatabaseHas('org_employee_phones', [
            'organization' => 'ЗДТГ',
            'last_name' => 'Бат',
            'first_name' => 'Болд',
        ]);

        $row = OrgEmployeePhone::first();
        $this->actingAs($admin)
            ->delete(route('phone-directory.staff.destroy', $row))
            ->assertRedirect(route('phone-directory.index', ['tab' => 'staff']));

        $this->assertDatabaseCount('org_employee_phones', 0);
    }
}
