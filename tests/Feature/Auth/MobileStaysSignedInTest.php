<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\WebauthnCredential;
use App\Support\AppLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Гар утсан дээр нэг нэвтэрсэн бол «Гарах» дартал нэвтэрсэн хэвээр байна.
 * Эргэж ирэхэд зөвхөн хуруу / царайгаар баталгаажуулна.
 */
class MobileStaysSignedInTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    public function test_the_session_lasts_at_least_a_month(): void
    {
        // .env-д богино утга байсан ч доод хязгаар мөрдөгдөнө.
        $this->assertGreaterThanOrEqual(30 * 24 * 60, (int) config('session.lifetime'));

        // Идэвхгүй байдлын түгжээ нь сессийн хугацаанаас хамаарахгүй.
        $this->assertSame(30, (int) config('session.idle_lock_minutes'));
    }

    public function test_a_phone_login_is_remembered_without_ticking_the_box(): void
    {
        $user = User::factory()->create([
            'phone' => '99112233',
            'password' => 'NuutsUg@1',
        ]);

        $response = $this->withHeader('User-Agent', self::PHONE_UA)
            ->post('/login', [
                'login' => '99112233',
                'password' => 'NuutsUg@1',
            ]);

        $response->assertRedirect();
        $this->assertAuthenticated();

        // «Намайг сана» чагтлаагүй ч сануулах күүки илгээгдэнэ.
        $this->assertTrue(
            $this->hasRememberCookie($response),
            'Гар утсан дээр «намайг сана» күүки илгээгдээгүй байна.',
        );
    }

    public function test_a_desktop_login_is_not_remembered_by_default(): void
    {
        $user = User::factory()->create([
            'phone' => '99112244',
            'password' => 'NuutsUg@1',
        ]);

        $response = $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0')
            ->post('/login', [
                'login' => '99112244',
                'password' => 'NuutsUg@1',
            ]);

        $response->assertRedirect();

        $this->assertFalse(
            $this->hasRememberCookie($response),
            'Компьютер дээр чагтлаагүй байхад сануулах күүки илгээгдсэн байна.',
        );
    }

    private function hasRememberCookie($response): bool
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_web')) {
                return $cookie->getValue() !== null && $cookie->getValue() !== '';
            }
        }

        return false;
    }

    public function test_returning_via_the_remember_cookie_asks_only_for_biometrics(): void
    {
        $user = User::factory()->create(['phone' => '99112255']);

        WebauthnCredential::query()->create([
            'user_id' => $user->id,
            'credential_id' => 'test-credential',
            'public_key' => 'test-key',
            'sign_count' => 0,
        ]);

        $request = \Illuminate\Http\Request::create('/dashboard');
        $request->headers->set('User-Agent', self::PHONE_UA);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $user);

        $middleware = new \App\Http\Middleware\EnsurePwaBiometricLock();

        // «Зөвхөн биометрик» түгжээ (сесс дуусаад күүкигээр эргэж нэвтэрсэн үе).
        AppLock::lock($request, AppLock::MODE_BIOMETRIC, idle: true);

        $this->assertTrue(AppLock::isLocked($request));
        $this->assertSame(AppLock::MODE_BIOMETRIC, AppLock::mode($request));

        // Гар утсан дээр энэ түгжээ автоматаар тайлагдахгүй.
        $middleware->handle($request, fn ($r) => response('ok'));

        $this->assertTrue(AppLock::isLocked($request));
        $this->assertSame(AppLock::MODE_BIOMETRIC, AppLock::mode($request));
    }
}
