<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RootAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Тохиргоо уншигдаагүй үед ч үндсэн супер админ бүрэн үүсэх ёстой.
 *
 * Deploy дээр migration нь `config:cache`-ээс өмнө ажилладаг тул шинэ
 * config файл кэшэнд байхгүй байж болно — тэр үед хоосон бүртгэл үүсэхгүй.
 */
class RootAdminRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_defaults_when_the_config_is_missing(): void
    {
        RootAdmin::find()?->forceDelete();

        config()->set('root_admin', []);

        $user = RootAdmin::ensure();

        $this->assertSame('77231111', $user->phone);
        $this->assertSame('admin@dornogovi.gov.mn', $user->email);
        $this->assertSame('Үндсэн супер админ', $user->name);
        $this->assertTrue(Hash::check('ZDTG@77231111', $user->password));
    }

    public function test_an_empty_admin_shell_is_repaired(): void
    {
        RootAdmin::find()?->forceDelete();

        // Хуучин алдаагаар үүссэн хоосон бүртгэл.
        DB::table('users')->insert([
            'name' => '',
            'email' => '',
            'phone' => null,
            'password' => Hash::make(''),
            'is_admin' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runRepairMigration();

        $root = RootAdmin::find();
        $this->assertNotNull($root);
        $this->assertSame('Үндсэн супер админ', $root->name);
        $this->assertSame('admin@dornogovi.gov.mn', $root->email);
        $this->assertTrue(Hash::check('ZDTG@77231111', $root->password));

        // Хоосон бүрхүүл үлдэхгүй.
        $this->assertSame(0, User::query()->where('email', '')->count());
    }

    private function runRepairMigration(): void
    {
        $migration = require database_path(
            'migrations/2026_09_09_140000_repair_root_admin_account.php'
        );

        $migration->up();
    }
}
