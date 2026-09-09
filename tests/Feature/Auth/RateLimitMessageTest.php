<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Хурдны хязгаарт хүрэхэд хар «429» дэлгэц биш, эелдэг мэдэгдэл гарна.
 */
class RateLimitMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('');
        config()->set('verify.enabled', true);
        config()->set('verify.api_key', 'vrf_test');
    }

    public function test_a_code_can_be_requested_many_times_in_a_row(): void
    {
        Http::fake(['api.verify.mn/*' => Http::response([
            'sessionId' => 'sess-1',
            'smsUri' => 'sms:144773?body=111111',
            'displayInstruction' => 'Илгээнэ үү.',
        ], 201)]);

        User::factory()->create(['phone' => '99112233']);

        // Өмнө нь 6 удаагийн дараа 429 гардаг байсан.
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('password.phone.send'), ['phone' => '99112233'])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }
    }

    public function test_the_limit_is_reported_in_mongolian_instead_of_a_black_page(): void
    {
        Http::fake(['api.verify.mn/*' => Http::response(['sessionId' => 's'], 201)]);

        User::factory()->create(['phone' => '99112233']);

        // Хязгаарыг давтал дуудна (30/мин).
        for ($i = 0; $i < 31; $i++) {
            $response = $this->post(route('password.phone.send'), ['phone' => '99112233']);
        }

        $response->assertRedirect();
        $response->assertSessionHasErrors('phone');

        $message = session('errors')->first('phone');
        $this->assertStringContainsString('дахин оролдоно уу', $message);
    }
}
