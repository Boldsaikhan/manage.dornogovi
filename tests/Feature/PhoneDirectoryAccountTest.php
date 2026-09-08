<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhoneDirectoryAccountTest extends TestCase
{
    use RefreshDatabase;

    private function entry(string $phone = '99112233'): PhoneDirectoryEntry
    {
        return PhoneDirectoryEntry::create([
            'org_name' => 'А хэлтэс',
            'org_order' => 1,
            'sort_order' => 1,
            'person_name' => 'Б.Болд',
            'position' => 'Мэргэжилтэн',
            'mobile_phone' => $phone,
        ]);
    }

    public function test_admin_can_reset_the_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = User::factory()->create(['phone' => '99112233', 'password' => Hash::make('huuchin')]);
        $entry = $this->entry();

        $this->actingAs($admin)
            ->patch(route('admin.phone-directory.account', $entry), ['password' => 'shine-nuuts'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('shine-nuuts', $staff->fresh()->password));
    }

    public function test_admin_can_change_the_login_and_the_directory_follows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = User::factory()->create(['phone' => '99112233']);
        $entry = $this->entry();

        $this->actingAs($admin)
            ->patch(route('admin.phone-directory.account', $entry), ['login' => '8800 1122'])
            ->assertSessionHasNoErrors();

        $this->assertSame('88001122', $staff->fresh()->phone);
        // Жагсаалтын дугаар нь ч зэрэг шинэчлэгдэнэ.
        $this->assertSame('88001122', $entry->fresh()->mobile_phone);
    }

    public function test_a_login_already_in_use_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['phone' => '99112233']);
        User::factory()->create(['phone' => '88001122']);
        $entry = $this->entry();

        $this->actingAs($admin)
            ->patch(route('admin.phone-directory.account', $entry), ['login' => '88001122'])
            ->assertSessionHasErrors('login');
    }

    public function test_an_account_is_created_when_none_exists(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $entry = $this->entry('91000000');

        $this->actingAs($admin)
            ->patch(route('admin.phone-directory.account', $entry), ['password' => 'shine-nuuts'])
            ->assertSessionHasNoErrors();

        $created = User::query()->where('phone', '91000000')->first();

        $this->assertNotNull($created, 'Бүртгэл үүсэх ёстой.');
        $this->assertSame('Б.Болд', $created->name);
        $this->assertTrue(Hash::check('shine-nuuts', $created->password));
        // И-мэйл автоматаар оноогдоно.
        $this->assertNotEmpty($created->email);
    }

    public function test_creating_without_a_password_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $entry = $this->entry('91000000');

        $this->actingAs($admin)
            ->patch(route('admin.phone-directory.account', $entry), ['login' => '91000000'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['phone' => '91000000']);
    }

    public function test_empty_request_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['phone' => '99112233']);
        $entry = $this->entry();

        $this->actingAs($admin)
            ->patch(route('admin.phone-directory.account', $entry), [])
            ->assertSessionHasErrors('login');
    }

    public function test_non_admin_cannot_touch_accounts(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['phone' => '99112233']);
        $entry = $this->entry();

        $this->actingAs($user)
            ->patch(route('admin.phone-directory.account', $entry), ['password' => 'shine-nuuts'])
            ->assertForbidden();
    }
}
