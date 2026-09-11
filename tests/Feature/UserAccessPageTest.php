<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Хандах эрх хуудас — зөвхөн байгаа албан хаагчид роль тааруулна.
 */
class UserAccessPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_be_created_from_this_page(): void
    {
        $this->assertFalse(
            app('router')->has('admin.users.store'),
            'Шинэ албан хаагч үүсгэх зам байх ёсгүй — утасны жагсаалтаас үүсгэнэ.',
        );
    }

    public function test_the_page_shows_the_directory_organisation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $staff = User::factory()->create(['name' => 'Б.Дөлгөөн', 'phone' => '99112233']);

        PhoneDirectoryEntry::create([
            'person_name' => 'Б.Дөлгөөн',
            'position' => 'Мэргэжилтэн',
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'mobile_phone' => '99112233',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($staff) {
                $row = collect($page->toArray()['props']['users'])
                    ->firstWhere('id', $staff->id);

                $this->assertSame(
                    'Төрийн захиргааны удирдлагын хэлтэс',
                    $row['directory_org'],
                );
            });
    }

    public function test_a_role_can_still_be_assigned(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = User::factory()->create(['is_admin' => false]);

        $role = Role::create(['key' => 'arhivch', 'label' => 'Архивч']);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.update', $staff), [
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'role_key' => $role->key,
                'permissions' => [],
            ])
            ->assertRedirect();

        $this->assertSame('arhivch', $staff->fresh()->role_key);
    }
}
