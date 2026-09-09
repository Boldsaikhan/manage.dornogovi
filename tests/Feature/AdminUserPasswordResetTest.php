<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * «Хандах эрх» хуудсаас албан хаагчийн нэвтрэх нууц үгийг шинэчлэх.
 */
class AdminUserPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create([
            'phone' => '99112233',
            'password' => 'HuuchinNuuts1',
        ]);
    }

    public function test_an_admin_can_set_a_new_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = $this->staff();

        $this->actingAs($admin)
            ->patch(route('admin.users.password', $staff), [
                'password' => 'ShineNuuts1',
                'notify' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('ShineNuuts1', $staff->fresh()->password));
    }

    public function test_the_new_password_can_be_sent_by_sms(): void
    {
        config()->set('sms.enabled', true);
        config()->set('sms.driver', 'log');
        Log::spy();

        $admin = User::factory()->create(['is_admin' => true]);
        $staff = $this->staff();

        $this->actingAs($admin)
            ->patch(route('admin.users.password', $staff), [
                'password' => 'ShineNuuts1',
                'notify' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context) => $message === 'SMS (log driver)'
                && str_contains($context['body'], 'ShineNuuts1'),
        );
    }

    public function test_a_short_password_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = $this->staff();

        $this->actingAs($admin)
            ->patch(route('admin.users.password', $staff), ['password' => 'богино'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('HuuchinNuuts1', $staff->fresh()->password));
    }

    public function test_the_login_name_must_be_the_directory_phone(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Ажлын дугаараар бүртгэгдсэн — жагсаалтад гар утас нь өөр.
        $staff = User::factory()->create(['name' => 'Ц.Энхтунгалаг', 'phone' => '88591973']);

        \App\Models\PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'person_name' => 'Цэрэн Энхтунгалаг',
            'position' => 'Мэргэжилтэн',
            'office_phone' => '88591973',
            'mobile_phone' => '99887766',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('users.1.directory_phone', '99887766')
                ->where('users.1.login_matches_directory', false));

        $this->actingAs($admin)
            ->patch(route('admin.users.login', $staff))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('99887766', $staff->fresh()->phone);
    }

    public function test_all_logins_can_be_synced_at_once(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'А.Админ']);
        $staff = User::factory()->create(['name' => 'Б.Болд', 'phone' => '70001111']);

        \App\Models\PhoneDirectoryEntry::create([
            'org_name' => 'Хэлтэс',
            'person_name' => 'Бат Болд',
            'office_phone' => '70001111',
            'mobile_phone' => '99112233',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.sync-logins'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('99112233', $staff->fresh()->phone);
    }

    public function test_a_taken_phone_is_not_stolen_from_another_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $staff = User::factory()->create(['name' => 'Б.Болд', 'phone' => '70001111']);
        User::factory()->create(['name' => 'Д.Дорж', 'phone' => '99112233']);

        \App\Models\PhoneDirectoryEntry::create([
            'org_name' => 'Хэлтэс',
            'person_name' => 'Бат Болд',
            'office_phone' => '70001111',
            'mobile_phone' => '99112233',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.login', $staff))
            ->assertSessionHasErrors('phone');

        $this->assertSame('70001111', $staff->fresh()->phone);
    }

    public function test_a_non_admin_cannot_reset_passwords(): void
    {
        $user = User::factory()->create();
        $staff = $this->staff();

        $this->actingAs($user)
            ->patch(route('admin.users.password', $staff), ['password' => 'ShineNuuts1'])
            ->assertForbidden();

        $this->assertTrue(Hash::check('HuuchinNuuts1', $staff->fresh()->password));
    }
}
