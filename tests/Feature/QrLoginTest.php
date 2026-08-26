<?php

namespace Tests\Feature;

use App\Models\LoginQrToken;
use App\Models\User;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_desktop_logs_in_after_phone_approves(): void
    {
        // Session-ыг cookie-гоор нь дуурайхын тулд шифрлэлтийг унтраана.
        $this->withoutMiddleware(EncryptCookies::class);

        $user = User::factory()->create();

        // 1. Компьютер токен үүсгэнэ.
        $create = $this->post(route('login.qr.create'));
        $create->assertOk();
        $token = $create->json('token');

        $this->assertNotEmpty($token);

        // array session driver нь хүсэлт бүрт шинэ session үүсгэдэг тул
        // компьютерийн session-ыг cookie-гоор нь барьж авна.
        $desktopSession = $this->app['session']->getId();

        // Зөвшөөрөх хүртэл хүлээнэ.
        $this->get(route('login.qr.status', $token))->assertJson(['status' => 'pending']);

        // 2. Утас зөвшөөрнө.
        $this->actingAs($user)->post(route('login.qr.approve', $token))->assertRedirect();

        $this->assertDatabaseHas('login_qr_tokens', [
            'token' => $token,
            'status' => LoginQrToken::APPROVED,
            'user_id' => $user->id,
        ]);

        // 3. Компьютер (анхны session) нэвтэрнэ.
        $this->app['auth']->guard()->logout();

        // Өөр session токеныг ашиглаж чадахгүй.
        $this->get(route('login.qr.status', $token))->assertJson(['status' => 'pending']);

        $this->withUnencryptedCookie(config('session.cookie'), $desktopSession)
            ->get(route('login.qr.status', $token))
            ->assertJson(['status' => 'approved']);

        $this->assertAuthenticatedAs($user);

        // 4. Токен нэг удаагийн — дахин ашиглагдахгүй.
        $this->assertDatabaseHas('login_qr_tokens', [
            'token' => $token,
            'status' => LoginQrToken::CONSUMED,
        ]);
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = LoginQrToken::create([
            'token' => LoginQrToken::generateToken(),
            'status' => LoginQrToken::PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('login.qr.status', $token->token))->assertJson(['status' => 'expired']);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('login.qr.approve', $token->token))
            ->assertSessionHasErrors('token');
    }

    public function test_approval_requires_authentication(): void
    {
        $create = $this->post(route('login.qr.create'));
        $token = $create->json('token');

        $this->post(route('login.qr.approve', $token))->assertRedirect(route('login'));
    }
}
