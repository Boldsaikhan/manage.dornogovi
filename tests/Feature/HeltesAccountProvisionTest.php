<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Services\HeltesAccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HeltesAccountProvisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_login_from_heltes_directory(): void
    {
        $this->heltesPerson(
            name: 'Ц.Сансармаа',
            position: 'Хэлтсийн дарга',
            mobile: '91116259',
            org: 'Төрийн захиргааны удирдлагын хэлтэс',
        );
        $this->heltesPerson(
            name: 'Б.Болдсайхан',
            position: 'Мэдээлэл технологийн мэргэжилтэн',
            mobile: '89239655',
            org: 'Төрийн захиргааны удирдлагын хэлтэс',
            sort: 2,
        );

        $result = app(HeltesAccountProvisioner::class)->run();

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['updated']);

        $head = User::query()->where('phone', '91116259')->first();
        $this->assertNotNull($head);
        $this->assertSame('Ц.Сансармаа', $head->name);
        $this->assertTrue($head->is_department_head);
        $this->assertFalse($head->is_specialist);
        $this->assertFalse($head->is_admin);
        $this->assertTrue(Hash::check('ZDTG91116259', $head->password));
        $this->assertSame('sansarmaa@dornogovi.gov.mn', $head->email);
        $this->assertTrue(
            UserModulePermission::query()->where('user_id', $head->id)->where('module_key', 'tasks')->exists()
        );

        $specialist = User::query()->where('phone', '89239655')->first();
        $this->assertTrue($specialist->is_specialist);
        $this->assertFalse($specialist->is_department_head);
        $this->assertTrue(Hash::check('ZDTG89239655', $specialist->password));
        $this->assertSame('boldsaikhan@dornogovi.gov.mn', $specialist->email);
    }

    public function test_email_uses_latin_name_and_avoids_collision(): void
    {
        $this->heltesPerson(name: 'А.Бадрал', mobile: '94588599');
        $this->heltesPerson(name: 'Б.Бадрал', mobile: '99110011', sort: 2);

        app(HeltesAccountProvisioner::class)->run();

        $first = User::query()->where('phone', '94588599')->first();
        $second = User::query()->where('phone', '99110011')->first();

        $this->assertSame('badral@dornogovi.gov.mn', $first->email);
        $this->assertSame('badral2@dornogovi.gov.mn', $second->email);
        $this->assertTrue(Hash::check('ZDTG94588599', $first->password));
    }

    public function test_sync_staff_emails_rewrites_phone_domain_without_resetting_password(): void
    {
        $this->heltesPerson(name: 'А.Бадрал', mobile: '94588599');

        $user = User::factory()->create([
            'name' => 'А.Бадрал',
            'email' => '94588599@staff.dornogovi.gov.mn',
            'phone' => '94588599',
            'password' => 'already-set-password',
        ]);

        $updated = app(HeltesAccountProvisioner::class)->syncStaffEmails();

        $this->assertSame(1, $updated);
        $user->refresh();
        $this->assertSame('badral@dornogovi.gov.mn', $user->email);
        $this->assertTrue(Hash::check('already-set-password', $user->password));
    }

    public function test_can_login_with_phone_and_generated_password(): void
    {
        $this->heltesPerson(
            name: 'Ц.Сансармаа',
            position: 'Хэлтсийн дарга',
            mobile: '91116259',
        );

        app(HeltesAccountProvisioner::class)->run();

        $this->post(route('login'), [
            'login' => '91116259',
            'password' => 'ZDTG91116259',
        ])->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame('91116259', auth()->user()->phone);
    }

    public function test_skips_missing_phone_and_non_heltes_rows(): void
    {
        $this->heltesPerson(name: 'Ц.Сансармаа', mobile: '91116259');
        PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'category' => 'heltes',
            'person_name' => 'Утасгүй Хүн',
            'position' => 'Мэргэжилтэн',
            'org_order' => 1,
            'sort_order' => 2,
        ]);
        PhoneDirectoryEntry::create([
            'org_name' => 'Сайншанд сум',
            'category' => 'sum',
            'person_name' => 'С.Сумдын',
            'position' => 'Засаг дарга',
            'mobile_phone' => '99112233',
            'org_order' => 2,
            'sort_order' => 1,
        ]);

        $result = app(HeltesAccountProvisioner::class)->run();

        $this->assertSame(1, $result['created']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame(1, User::query()->whereNotNull('phone')->count());
        $this->assertNull(User::query()->where('phone', '99112233')->first());
    }

    public function test_duplicate_phone_is_skipped_and_existing_non_admin_password_is_reset(): void
    {
        $this->heltesPerson(name: 'Ц.Сансармаа', mobile: '91116259');
        $this->heltesPerson(name: 'Өөр Хүн', mobile: '91116259', sort: 2);

        $existing = User::factory()->create([
            'name' => 'Хуучин',
            'phone' => '91116259',
            'password' => 'old-password',
        ]);

        $result = app(HeltesAccountProvisioner::class)->run();

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertCount(1, $result['skipped']);

        $existing->refresh();
        $this->assertSame('Ц.Сансармаа', $existing->name);
        $this->assertTrue(Hash::check('ZDTG91116259', $existing->password));
        $this->assertSame(1, User::query()->where('phone', '91116259')->count());
    }

    public function test_reapplies_permissions_when_updating_existing_user(): void
    {
        $this->heltesPerson(name: 'А.Номин', mobile: '99178904');

        $existing = User::factory()->create([
            'name' => 'А.Номин',
            'phone' => '99178904',
            'password' => 'old-password',
            'is_specialist' => true,
        ]);

        $result = app(HeltesAccountProvisioner::class)->run();

        $this->assertSame(1, $result['updated']);
        $existing->refresh();
        $this->assertTrue(Hash::check('ZDTG99178904', $existing->password));
        $this->assertTrue(
            UserModulePermission::query()->where('user_id', $existing->id)->where('module_key', 'tasks')->exists()
        );
    }

    public function test_sets_the_same_login_password_for_every_user(): void
    {
        $staff = User::factory()->create(['password' => 'old-staff', 'is_admin' => false]);
        $admin = User::factory()->create(['password' => 'old-admin', 'is_admin' => true]);

        $updated = app(HeltesAccountProvisioner::class)->setAllLoginPasswords('ZDTG@22026');

        $this->assertSame(2, $updated);
        $this->assertTrue(Hash::check('ZDTG@22026', $staff->fresh()->password));
        $this->assertTrue(Hash::check('ZDTG@22026', $admin->fresh()->password));
        $this->assertNull($staff->fresh()->remember_token);
    }

    public function test_sets_login_password_only_for_super_admins(): void
    {
        $staff = User::factory()->create(['password' => 'ZDTG@22026', 'is_admin' => false]);
        $admin = User::factory()->create(['password' => 'ZDTG@22026', 'is_admin' => true]);

        $updated = app(HeltesAccountProvisioner::class)->setAdminLoginPasswords('Boldoo@1134');

        $this->assertSame(1, $updated);
        $this->assertTrue(Hash::check('ZDTG@22026', $staff->fresh()->password));
        $this->assertTrue(Hash::check('Boldoo@1134', $admin->fresh()->password));
        $this->assertNull($admin->fresh()->remember_token);
    }

    public function test_does_not_overwrite_admin_password(): void
    {
        $this->heltesPerson(name: 'Ц.Сансармаа', mobile: '91116259');

        $admin = User::factory()->create([
            'name' => 'Админ',
            'phone' => '91116259',
            'password' => 'admin-secret',
            'is_admin' => true,
        ]);

        app(HeltesAccountProvisioner::class)->run();

        $admin->refresh();
        $this->assertTrue(Hash::check('admin-secret', $admin->password));
        $this->assertTrue($admin->is_admin);
        $this->assertSame('Ц.Сансармаа', $admin->name);
    }

    public function test_dry_run_does_not_write(): void
    {
        $this->heltesPerson(name: 'Ц.Сансармаа', mobile: '91116259');

        $result = app(HeltesAccountProvisioner::class)->run(true);

        $this->assertSame(1, $result['created']);
        $this->assertTrue($result['dry_run']);
        $this->assertSame(0, User::query()->where('phone', '91116259')->count());
    }

    public function test_admin_endpoint_provisions_accounts(): void
    {
        $this->heltesPerson(name: 'Ц.Сансармаа', mobile: '91116259');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.users.provision-heltes'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(User::query()->where('phone', '91116259')->exists());
    }

    public function test_non_admin_cannot_provision(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post(route('admin.users.provision-heltes'))
            ->assertForbidden();
    }

    public function test_artisan_command_provisions_accounts(): void
    {
        $this->heltesPerson(name: 'Ц.Сансармаа', mobile: '91116259');

        $this->artisan('users:provision-heltes')
            ->expectsOutputToContain('Шинэ: 1')
            ->assertSuccessful();

        $this->assertTrue(User::query()->where('phone', '91116259')->exists());
    }

    public function test_artisan_emails_only_rewrites_staff_domain(): void
    {
        $this->heltesPerson(name: 'А.Бадрал', mobile: '94588599');
        User::factory()->create([
            'name' => 'А.Бадрал',
            'email' => '94588599@staff.dornogovi.gov.mn',
            'phone' => '94588599',
        ]);

        $this->artisan('users:provision-heltes', ['--emails-only' => true])
            ->expectsOutputToContain('И-мэйл шинэчилсэн: 1')
            ->assertSuccessful();

        $this->assertSame(
            'badral@dornogovi.gov.mn',
            User::query()->where('phone', '94588599')->value('email'),
        );
    }

    private function heltesPerson(
        string $name,
        string $mobile = '91116259',
        string $position = 'Мэргэжилтэн',
        string $org = 'Төрийн захиргааны удирдлагын хэлтэс',
        int $sort = 1,
    ): PhoneDirectoryEntry {
        return PhoneDirectoryEntry::create([
            'org_name' => $org,
            'category' => 'heltes',
            'person_name' => $name,
            'position' => $position,
            'mobile_phone' => $mobile,
            'org_order' => 1,
            'sort_order' => $sort,
        ]);
    }
}
