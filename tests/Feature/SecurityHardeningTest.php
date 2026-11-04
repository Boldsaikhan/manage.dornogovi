<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Гаднаас халдахаас хамгаалах үндсэн тогтолцоо.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_carries_the_protective_headers(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame('none', $response->headers->get('X-Permitted-Cross-Domain-Policies'));
        $this->assertStringContainsString('geolocation=()', (string) $response->headers->get('Permissions-Policy'));
        $this->assertSame(
            'same-origin-allow-popups',
            $response->headers->get('Cross-Origin-Opener-Policy'),
        );
    }

    public function test_the_enforced_policy_blocks_the_classic_attacks(): void
    {
        $csp = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

        // Clickjacking — өөр сайтын iframe-д орохгүй.
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        // <base> тарих, plugin ажиллуулахыг хориглоно.
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        // Маягтыг http эсвэл javascript: руу илгээхгүй.
        $this->assertStringContainsString("form-action 'self' https:", $csp);
        $this->assertStringContainsString('upgrade-insecure-requests', $csp);
    }

    public function test_the_strict_policy_is_still_only_reporting(): void
    {
        $response = $this->get(route('login'));

        $report = (string) $response->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString("default-src 'self'", $report);
        $this->assertStringContainsString('report-uri /csp-report', $report);
    }

    public function test_a_violation_report_is_logged_without_a_csrf_token(): void
    {
        Log::spy();

        $this->postJson('/csp-report', [
            'csp-report' => [
                'effective-directive' => 'script-src',
                'blocked-uri' => 'https://evil.example/x.js',
                'document-uri' => 'https://manage.dornogovi.gov.mn/uureg',
            ],
        ])->assertNoContent();

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_junk_report_is_ignored(): void
    {
        $this->postJson('/csp-report', ['хог' => 'утга'])->assertNoContent();
    }

    public function test_a_weak_password_is_refused(): void
    {
        $rule = ['password' => [Password::defaults()]];

        $this->assertTrue(Validator::make(['password' => 'Nuuts2026Ug'], $rule)->passes());

        // Богино, зөвхөн үсэг, зөвхөн тоо — бүгд унана.
        foreach (['password', 'Nuuts2026', 'parolparol', '1234567890'] as $weak) {
            $this->assertFalse(
                Validator::make(['password' => $weak], $rule)->passes(),
                "«{$weak}» нууц үг давчихлаа.",
            );
        }
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        User::factory()->create(['email' => 'hello@dornogovi.gov.mn']);

        Event::fake([Lockout::class]);

        // Дараалсан буруу оролдлого хийвэл түгжигдэх ёстой.
        for ($i = 0; $i < 8; $i++) {
            $this->from(route('login'))->post(route('login'), [
                'login' => 'hello@dornogovi.gov.mn',
                'password' => 'buruu-nuuts-ug',
            ]);
        }

        Event::assertDispatched(Lockout::class);
        $this->assertGuest();
    }

    public function test_the_admin_area_is_closed_to_ordinary_staff(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.logs'))
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        foreach (['dept.dashboard', 'tasks.index', 'phone-directory.index'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }
}
