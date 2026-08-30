<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use App\Services\HeltesAccountProvisioner;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmsSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_driver_sends_login_credentials(): void
    {
        config([
            'sms.enabled' => true,
            'sms.driver' => 'log',
            'app.name' => 'manage test',
            'app.url' => 'https://manage.example.test',
        ]);

        Log::spy();

        $user = User::factory()->create([
            'phone' => '99112233',
            'name' => 'Б.Тест',
        ]);

        $sender = app(SmsSender::class);

        $this->assertTrue($sender->sendLoginCredentials($user, 'ZDTG@2026'));
        $this->assertSame('97699112233', $sender->formatPhoneForApi('99112233'));

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'SMS (log driver)'
                    && $context['to'] === '97699112233'
                    && str_contains($context['body'], 'ZDTG@2026')
                    && str_contains($context['body'], '99112233');
            });
    }

    public function test_http_driver_posts_to_configured_api(): void
    {
        config([
            'sms.enabled' => true,
            'sms.driver' => 'http',
            'sms.http.url' => 'https://sms.example.test/send',
            'sms.http.token' => 'secret-token',
            'sms.http.phone_field' => 'to',
            'sms.http.message_field' => 'text',
        ]);

        Http::fake([
            'sms.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create(['phone' => '88112233']);

        $this->assertTrue(app(SmsSender::class)->sendLoginCredentials($user, 'Pass1234!'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.example.test/send'
                && $request->hasHeader('Authorization', 'Bearer secret-token')
                && $request['to'] === '97688112233'
                && str_contains($request['text'], 'Pass1234!');
        });
    }

    public function test_disabled_sender_does_not_log(): void
    {
        config(['sms.enabled' => false]);

        Log::spy();

        $user = User::factory()->create(['phone' => '99112233']);

        $this->assertFalse(app(SmsSender::class)->sendLoginCredentials($user, 'ZDTG@2026'));

        Log::shouldNotHaveReceived('info');
    }

    public function test_provision_sends_sms_for_new_accounts_when_enabled(): void
    {
        config([
            'sms.enabled' => true,
            'sms.driver' => 'log',
            'sms.send_on_provision' => true,
        ]);

        Log::spy();

        PhoneDirectoryEntry::create([
            'org_name' => 'Тест хэлтэс',
            'category' => 'heltes',
            'person_name' => 'Ц.Сансармаа',
            'position' => 'Мэргэжилтэн',
            'mobile_phone' => '91116259',
            'org_order' => 1,
            'sort_order' => 1,
        ]);

        $result = app(HeltesAccountProvisioner::class)->run();

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['sms_sent']);
        $this->assertSame(0, $result['sms_failed']);

        Log::shouldHaveReceived('info')->once();
    }
}
