<?php

namespace Tests\Feature\Auth;

use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Нууц үгээ утсаараа сэргээх — verify.mn (MO SMS).
 *
 * Хэрэглэгч ӨӨРӨӨ 144773 руу кодоо илгээнэ. Бид зөвхөн session үүсгээд,
 * төлвийг нь эх сурвалжаас нь асууна.
 */
class PhonePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION_ID = '4d4c95ff-0fd4-4d9e-900d-6d18fb8ce6a7';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('verify.enabled', true);
        config()->set('verify.api_key', 'vrf_test');
        config()->set('verify.callback_secret', 'secret-token');
    }

    private function user(): User
    {
        return User::factory()->create([
            'phone' => '99112233',
            'password' => 'HuuchinNuuts1',
        ]);
    }

    /** verify.mn-ийн баримтад заасан хариу. */
    private function sessionResponse(string $code = '482916'): array
    {
        return [
            'sessionId' => self::SESSION_ID,
            'phone' => '99112233',
            'shortcode' => '144773',
            'text' => $code,
            'smsUri' => 'sms:144773?body='.$code,
            'displayInstruction' => 'Та өөрийн 99112233 дугаараас 144773 дугаарт "'.$code.'" гэж SMS илгээнэ үү.',
            'expiresAt' => Carbon::now()->addMinutes(5)->toIso8601ZuluString(),
        ];
    }

    public function test_a_session_is_created_and_the_user_is_told_to_send_the_sms(): void
    {
        Http::fake(['api.verify.mn/sessions' => Http::response($this->sessionResponse(), 201)]);

        $this->user();

        $this->post(route('password.phone.send'), ['phone' => '9911-2233'])->assertRedirect();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.verify.mn/sessions'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer vrf_test')
                && $request['phone'] === '99112233'
                && preg_match('/^\d{6}$/', (string) $request['text']) === 1
                && str_contains((string) $request['callback'], 'secret-token');
        });

        $record = PhoneVerification::query()->firstOrFail();
        $this->assertSame(self::SESSION_ID, $record->session_id);
        $this->assertSame('verify.mn', $record->channel);
        $this->assertSame('sms:144773?body=482916', $record->sms_uri);

        // Хуудсан дээр заавар, sms: холбоос, богино дугаар гарна.
        $this->get(route('password.request'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('phoneState.step', 'code')
                ->where('phoneState.channel', 'verify.mn')
                ->where('phoneState.sms_uri', 'sms:144773?body=482916')
                ->where('phoneState.shortcode', '144773')
                ->where('phoneState.sms_cost', 150)
                ->where('phoneState.instruction', fn (string $text) => str_contains($text, '144773')));
    }

    public function test_the_status_check_trusts_only_the_official_session_state(): void
    {
        Http::fake([
            'api.verify.mn/sessions' => Http::response($this->sessionResponse(), 201),
            'api.verify.mn/sessions/*' => Http::sequence()
                ->push(['sessionStatus' => 'PENDING'])
                ->push(['sessionStatus' => 'VERIFIED']),
        ]);

        $user = $this->user();
        $this->post(route('password.phone.send'), ['phone' => '99112233']);

        // Хэрэглэгч SMS-ээ хараахан илгээгээгүй.
        $this->get(route('password.phone.status'))->assertOk()->assertJson(['verified' => false]);

        // Илгээсний дараа verify.mn VERIFIED гэж хариулна.
        $this->get(route('password.phone.status'))->assertOk()->assertJson(['verified' => true]);

        $this->get(route('password.request'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('phoneState.step', 'password'));

        $this->post(route('password.phone.update'), [
            'password' => 'ShineNuuts1',
            'password_confirmation' => 'ShineNuuts1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('ShineNuuts1', $user->fresh()->password));
    }

    public function test_the_callback_only_wakes_us_up_and_we_re_check_the_status(): void
    {
        Http::fake([
            'api.verify.mn/sessions' => Http::response($this->sessionResponse(), 201),
            'api.verify.mn/sessions/*' => Http::response(['sessionStatus' => 'VERIFIED']),
        ]);

        $this->user();
        $this->post(route('password.phone.send'), ['phone' => '99112233']);

        // verify.mn нь GET-ээр, бие агуулгагүй дуудна.
        $this->get(route('verify.callback', ['secret' => 'secret-token']))->assertOk();

        $this->assertNotNull(PhoneVerification::query()->firstOrFail()->verified_at);

        // Төлвийг эх сурвалжаас нь давхар асуусан байх ёстой.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sessions/'.self::SESSION_ID)
            && $request->method() === 'GET');
    }

    public function test_a_callback_is_ignored_when_the_session_is_not_verified(): void
    {
        Http::fake([
            'api.verify.mn/sessions' => Http::response($this->sessionResponse(), 201),
            'api.verify.mn/sessions/*' => Http::response(['sessionStatus' => 'PENDING']),
        ]);

        $this->user();
        $this->post(route('password.phone.send'), ['phone' => '99112233']);

        $this->get(route('verify.callback', ['secret' => 'secret-token']))->assertOk();

        $this->assertNull(PhoneVerification::query()->firstOrFail()->verified_at);
    }

    public function test_a_wrong_callback_secret_is_not_found(): void
    {
        Http::fake(['api.verify.mn/sessions' => Http::response($this->sessionResponse(), 201)]);

        $this->user();
        $this->post(route('password.phone.send'), ['phone' => '99112233']);

        $this->get(route('verify.callback', ['secret' => 'buruu']))->assertNotFound();

        $this->assertNull(PhoneVerification::query()->firstOrFail()->verified_at);
    }

    public function test_typing_the_code_proves_nothing_in_the_mo_flow(): void
    {
        Http::fake(['api.verify.mn/sessions' => Http::response($this->sessionResponse(), 201)]);

        $this->user();
        $this->post(route('password.phone.send'), ['phone' => '99112233']);

        $this->post(route('password.phone.confirm'), ['code' => '482916'])
            ->assertSessionHasErrors('code');

        $this->assertNull(PhoneVerification::query()->firstOrFail()->verified_at);
    }

    public function test_an_unknown_phone_looks_the_same_but_creates_no_session(): void
    {
        Http::fake();

        $this->post(route('password.phone.send'), ['phone' => '88001122'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertNothingSent();
    }

    public function test_an_expired_session_sends_the_user_back_to_the_start(): void
    {
        Http::fake([
            'api.verify.mn/sessions' => Http::response($this->sessionResponse(), 201),
            'api.verify.mn/sessions/*' => Http::response(['sessionStatus' => 'EXPIRED']),
        ]);

        $this->user();
        $this->post(route('password.phone.send'), ['phone' => '99112233']);

        $this->get(route('password.phone.status'))
            ->assertOk()
            ->assertJson(['verified' => false, 'expired' => true]);

        $this->get(route('password.request'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('phoneState.step', 'phone'));
    }

    public function test_the_password_cannot_be_changed_before_verification(): void
    {
        Http::fake([
            'api.verify.mn/sessions' => Http::response($this->sessionResponse(), 201),
            'api.verify.mn/sessions/*' => Http::response(['sessionStatus' => 'PENDING']),
        ]);

        $user = $this->user();
        $this->post(route('password.phone.send'), ['phone' => '99112233']);

        $this->post(route('password.phone.update'), [
            'password' => 'ShineNuuts1',
            'password_confirmation' => 'ShineNuuts1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('HuuchinNuuts1', $user->fresh()->password));
    }

    public function test_it_refuses_when_no_channel_is_configured(): void
    {
        // verify.mn ч, SMS ч тохируулаагүй — код хаашаа ч очихгүй.
        config()->set('verify.enabled', false);
        config()->set('sms.enabled', false);

        $this->user();

        $this->post(route('password.phone.send'), ['phone' => '99112233'])
            ->assertSessionHasErrors('phone');

        // Бүртгэлгүй дугаарт ч ижил хариу — задруулахгүй.
        $this->post(route('password.phone.send'), ['phone' => '88001122'])
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, PhoneVerification::query()->count());
    }

    public function test_the_fallback_channel_still_lets_the_user_type_the_code(): void
    {
        // verify.mn унтраалттай — код бидний зүгээс SMS-ээр очно.
        config()->set('verify.enabled', false);
        config()->set('sms.enabled', true);
        config()->set('sms.driver', 'log');

        $user = $this->user();

        $this->post(route('password.phone.send'), ['phone' => '99112233'])->assertRedirect();

        $record = PhoneVerification::query()->firstOrFail();
        $this->assertSame('sms', $record->channel);

        $this->post(route('password.phone.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, $record->fresh()->attempts);
    }
}
