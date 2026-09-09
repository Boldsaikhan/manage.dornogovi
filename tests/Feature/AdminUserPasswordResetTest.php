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
