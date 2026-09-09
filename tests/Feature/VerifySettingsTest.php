<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Verify\VerifySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * verify.mn-ийн тохиргоог «Системийн тохиргоо» хуудаснаас удирдана.
 */
class VerifySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_an_admin_can_configure_verify_mn_without_touching_the_env(): void
    {
        config()->set('verify.enabled', false);
        config()->set('verify.api_key', null);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('admin.verify-settings.update'), [
                'enabled' => true,
                'api_key' => 'vrf_test_key',
                'shortcode' => '144773',
                'response_sms' => 'Dornogovi ZDTG: batalgaajlaa.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = app(VerifySettings::class);

        $this->assertTrue($settings->enabled());
        $this->assertSame('vrf_test_key', $settings->apiKey());
        $this->assertTrue($settings->isConfigured());

        // Түлхүүр нь ил хадгалагдахгүй.
        $this->assertNotSame('vrf_test_key', $settings->get(VerifySettings::KEY_API_KEY));
    }

    public function test_the_admin_page_shows_the_state_without_leaking_the_key(): void
    {
        $admin = $this->admin();
        app(VerifySettings::class)->setApiKey('vrf_super_secret_value');

        $this->actingAs($admin)
            ->get(route('admin.systems.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('verify.has_api_key', true)
                ->where('verify.api_key_hint', fn (string $hint) => ! str_contains($hint, 'secret_value'))
                ->where('verify.callback_url', fn (string $url) => str_contains($url, '/webhooks/verify-mn/'))
                ->where('verify.shortcode', '144773'));
    }

    public function test_the_saved_key_is_used_when_creating_a_session(): void
    {
        config()->set('verify.enabled', false);
        config()->set('verify.api_key', null);

        Http::fake(['api.verify.mn/sessions' => Http::response([
            'sessionId' => 'sess-1',
            'smsUri' => 'sms:144773?body=111111',
            'displayInstruction' => 'Илгээнэ үү.',
        ], 201)]);

        $settings = app(VerifySettings::class);
        $settings->set(VerifySettings::KEY_ENABLED, '1');
        $settings->setApiKey('vrf_from_database');

        User::factory()->create(['phone' => '99112233']);

        $this->post(route('password.phone.send'), ['phone' => '99112233'])->assertRedirect();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer vrf_from_database'));
    }

    public function test_the_callback_secret_is_generated_and_can_be_rotated(): void
    {
        config()->set('verify.callback_secret', null);

        $settings = app(VerifySettings::class);

        $first = $settings->callbackSecret();
        $this->assertNotSame('', $first);
        $this->assertSame($first, $settings->callbackSecret(), 'Дуудах бүрд шинэ утга үүсгэж болохгүй.');

        $this->actingAs($this->admin())
            ->patch(route('admin.verify-settings.update'), [
                'shortcode' => '144773',
                'regenerate_secret' => true,
            ])
            ->assertRedirect();

        $this->assertNotSame($first, app(VerifySettings::class)->callbackSecret());
    }

    public function test_a_non_admin_cannot_change_the_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('admin.verify-settings.update'), ['shortcode' => '144773'])
            ->assertForbidden();
    }
}
