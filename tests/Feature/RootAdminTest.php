<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RootAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Үндсэн супер админ — устгагдахгүй, эрх нь хасагдахгүй бүртгэл.
 */
class RootAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_admin_is_created_with_the_configured_login(): void
    {
        $user = RootAdmin::ensure((string) config('root_admin.password'));

        $this->assertSame('77231111', $user->phone);
        $this->assertTrue((bool) $user->is_admin);
        $this->assertTrue(Hash::check('ZDTG@77231111', $user->password));

        // Дахин дуудахад хуулбар үүсгэхгүй.
        RootAdmin::ensure();
        $this->assertSame(1, User::query()->where('phone', '77231111')->count());
    }

    public function test_the_migration_creates_it_and_lost_rights_are_restored(): void
    {
        // Migration ажилласны дараа бүртгэл аль хэдийн байгаа.
        $root = RootAdmin::find();
        $this->assertNotNull($root);

        // Ямар нэг байдлаар эрх нь хасагдвал сэргээнэ.
        $root->forceFill(['is_admin' => false])->save();
        RootAdmin::ensure();

        $this->assertTrue((bool) $root->fresh()->is_admin);
        $this->assertSame(1, User::query()->where('phone', '77231111')->count());
    }

    public function test_the_super_admin_right_cannot_be_removed(): void
    {
        $root = RootAdmin::ensure((string) config('root_admin.password'));
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $root), [
                'name' => $root->name,
                'email' => $root->email,
                'phone' => '99999999',
                'is_admin' => false,
                'permissions' => [],
            ])
            ->assertRedirect();

        $fresh = $root->fresh();
        $this->assertTrue((bool) $fresh->is_admin, 'Супер админ эрх хасагдсан байна.');
        $this->assertSame('77231111', $fresh->phone, 'Нэвтрэх нэр өөрчлөгдсөн байна.');
    }

    public function test_the_login_is_not_synced_from_the_phone_directory(): void
    {
        $root = RootAdmin::ensure();
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::create([
            'org_name' => 'ЗДТГ',
            'person_name' => $root->name,
            'office_phone' => '77231111',
            'mobile_phone' => '99887766',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.login', $root))
            ->assertSessionHasErrors('phone');

        $this->actingAs($admin)->post(route('admin.users.sync-logins'))->assertRedirect();

        $this->assertSame('77231111', $root->fresh()->phone);
    }

    public function test_the_root_admin_can_log_in_with_the_configured_password(): void
    {
        RootAdmin::ensure((string) config('root_admin.password'));

        $this->post('/login', [
            'login' => '77231111',
            'password' => 'ZDTG@77231111',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }
}
