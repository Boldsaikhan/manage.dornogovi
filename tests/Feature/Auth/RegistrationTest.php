<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Өөрөө бүртгүүлэх боломж хаалттай байх ёстой.
 *
 * Энэ бол дотоод систем — бүртгэлийг зөвхөн админ «Хандах эрх»
 * хэсгээс үүсгэнэ.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_screen_is_gone(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_nobody_can_create_an_account_from_outside(): void
    {
        $this->post('/register', [
            'name' => 'Гаднын хүн',
            'email' => 'attacker@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_the_route_name_no_longer_exists(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('register'));
    }

    public function test_an_admin_still_creates_accounts(): void
    {
        // Админаар үүсгэх зам нь хэвээр ажиллана.
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.users.index'));
    }
}
