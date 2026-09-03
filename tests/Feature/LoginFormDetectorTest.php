<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use App\Support\LoginFormDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginFormDetectorTest extends TestCase
{
    use RefreshDatabase;

    private function system(string $url = 'https://erp.example.mn/login'): System
    {
        return System::create([
            'slug' => 'erp',
            'name' => 'Тест ERP',
            'url' => 'https://erp.example.mn',
            'login_url' => $url,
            'login_method' => System::LOGIN_MANUAL,
            'requires_login' => true,
            'is_active' => true,
            'is_internal' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_a_simple_form_is_detected(): void
    {
        Http::fake([
            '*' => Http::response(<<<'HTML'
                <html><body>
                <form method="POST" action="/do-login">
                    <input type="hidden" name="lang" value="mn">
                    <input type="text" name="user_email">
                    <input type="password" name="user_password">
                    <button type="submit">Нэвтрэх</button>
                </form>
                </body></html>
            HTML),
        ]);

        $result = app(LoginFormDetector::class)->detect('https://erp.example.mn/login');

        $this->assertTrue($result['ok']);
        $this->assertSame('https://erp.example.mn/do-login', $result['action']);
        $this->assertSame('user_email', $result['username_field']);
        $this->assertSame('user_password', $result['password_field']);
        $this->assertSame(['lang' => 'mn'], $result['extra_fields']);
        $this->assertSame([], $result['dynamic_fields']);
    }

    public function test_csrf_like_fields_are_flagged_as_dynamic(): void
    {
        Http::fake([
            '*' => Http::response(<<<'HTML'
                <form method="post" action="https://mail.example.mn/service/login">
                    <input type="hidden" name="login_csrf" value="7f3a9c1b2e8d4a6f0b5c9e2d1a7f4b3c">
                    <input type="text" name="username">
                    <input type="password" name="password">
                </form>
            HTML),
        ]);

        $result = app(LoginFormDetector::class)->detect('https://mail.example.mn/');

        $this->assertTrue($result['ok']);
        $this->assertContains('login_csrf', $result['dynamic_fields']);
    }

    public function test_a_javascript_login_page_is_reported(): void
    {
        Http::fake(['*' => Http::response('<html><body><div id="app"></div></body></html>')]);

        $result = app(LoginFormDetector::class)->detect('https://spa.example.mn/login');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('олдсонгүй', $result['reason']);
    }

    public function test_get_forms_are_rejected(): void
    {
        Http::fake([
            '*' => Http::response('<form method="get"><input name="u"><input type="password" name="p"></form>'),
        ]);

        $result = app(LoginFormDetector::class)->detect('https://x.example.mn/login');

        $this->assertFalse($result['ok']);
    }

    public function test_admin_saves_the_detected_config(): void
    {
        Http::fake([
            '*' => Http::response('<form method="POST" action="/api/login"><input name="ner"><input type="password" name="nuuts"></form>'),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $system = $this->system();

        $this->actingAs($admin)
            ->post(route('admin.systems.detect-login', $system->id))
            ->assertRedirect();

        $system->refresh();

        $this->assertSame(System::LOGIN_FORM_POST, $system->login_method);
        $this->assertSame('https://erp.example.mn/api/login', $system->login_form_action);
        $this->assertSame('ner', $system->login_username_field);
        $this->assertSame('nuuts', $system->login_password_field);
        $this->assertTrue($system->canAutoSubmit());
    }
}
