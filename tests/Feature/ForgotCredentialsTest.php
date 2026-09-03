<?php

namespace Tests\Feature;

use App\Mail\LoginCredentialsMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_emailed_and_password_is_reset(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'badral@dornogovi.gov.mn',
            'phone' => '99112233',
            'password' => Hash::make('huuchin-nuuts'),
        ]);

        $this->post(route('password.email'), ['email' => 'Badral@Dornogovi.gov.mn'])
            ->assertRedirect()
            ->assertSessionHas('status');

        // Хуучин нууц үг хүчингүй болсон.
        $this->assertFalse(Hash::check('huuchin-nuuts', $user->fresh()->password));

        Mail::assertSent(LoginCredentialsMail::class, function (LoginCredentialsMail $mail) use ($user) {
            // Илгээсэн түр нууц үг нь шинэ hash-тай таарна.
            return $mail->hasTo($user->email)
                && Hash::check($mail->temporaryPassword, $user->fresh()->password);
        });
    }

    public function test_mail_renders(): void
    {
        $user = User::factory()->create([
            'email' => 'badral@dornogovi.gov.mn',
            'phone' => '99112233',
        ]);

        $html = (new LoginCredentialsMail($user, 'Tur9Nuuts2'))->render();

        $this->assertStringContainsString('Tur9Nuuts2', $html);
        $this->assertStringContainsString('99112233', $html);
    }

    public function test_unknown_email_does_not_reveal_anything(): void
    {
        Mail::fake();

        $this->post(route('password.email'), ['email' => 'baihgui@dornogovi.gov.mn'])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_email_is_required(): void
    {
        $this->post(route('password.email'), ['email' => 'buruu'])
            ->assertSessionHasErrors('email');
    }
}
