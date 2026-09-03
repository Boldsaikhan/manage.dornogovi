<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginCredentialsMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * «Нууц үгээ мартсан уу?» нь reset-холбоос илгээхийн оронд бүртгэлтэй и-мэйл рүү
 * нэвтрэх нэр + шинэ түр нууц үг илгээдэг болсон.
 *
 * reset-password/{token} маршрут хэвээр байгаа тул түүнийг ч шалгана.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertStatus(200);
    }

    public function test_login_credentials_are_emailed_instead_of_a_reset_link(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Mail::assertSent(LoginCredentialsMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get('/reset-password/'.$token)->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));
    }
}
