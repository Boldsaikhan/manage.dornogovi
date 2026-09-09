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
 * Утасны дугаараар нууц үг сэргээх — verify.mn-ээр код илгээнэ.
 */
class PhonePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('verify.enabled', true);
        config()->set('verify.api_key', 'test-key');
        config()->set('verify.callback_secret', 'secret-token');
    }

    private function user(): User
    {
        return User::factory()->create([
            'phone' => '99112233',
            'password' => 'HuuchinNuuts1',
        ]);
    }

    public function test_a_session_is_created_at_verify_mn_and_the_code_unlocks_the_form(): void
    {
        Http::fake([
            'api.verify.mn/*' => Http::response(['id' => 'sess_123'], 201),
        ]);

        $user = $this->user();

        $this->post(route('password.phone.send'), ['phone' => '9911-2233'])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.verify.mn/sessions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['phone'] === '99112233'
                && preg_match('/^\d{6}$/', (string) $request['text']) === 1
                && str_contains((string) $request['callback'], 'secret-token');
        });

        $record = PhoneVerification::query()->firstOrFail();
        $this->assertSame('sess_123', $record->session_id);
        $this->assertNull($record->verified_at);

        // Кодыг hash-аар хадгална — задалж уншихгүй.
        $this->assertNotSame('', $record->code_hash);

        $this->get(route('password.request'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('phoneState.step', 'code'));

        // Буруу код — алхам ахихгүй.
        $this->post(route('password.phone.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertSame(1, $record->fresh()->attempts);

        $code = $this->codeFor($record);

        $this->post(route('password.phone.confirm'), ['code' => $code])
            ->assertRedirect();

        $this->assertNotNull($record->fresh()->verified_at);

        $this->get(route('password.request'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('phoneState.step', 'password'));

        $this->post(route('password.phone.update'), [
            'password' => 'ShineNuuts1',
            'password_confirmation' => 'ShineNuuts1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('ShineNuuts1', $user->fresh()->password));
        $this->assertNotNull($record->fresh()->consumed_at);
    }

    public function test_the_verify_mn_callback_can_confirm_the_code(): void
    {
        Http::fake(['api.verify.mn/*' => Http::response(['id' => 'sess_777'], 201)]);

        $this->user();
        $this->post(route('password.phone.send'), ['phone' => '99112233']);

        $this->get(route('password.phone.status'))
            ->assertOk()
            ->assertJson(['verified' => false]);

        // verify.mn мэдэгдэл илгээнэ.
        $this->postJson(route('verify.callback', ['secret' => 'secret-token']), [
            'id' => 'sess_777',
            'phone' => '99112233',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->get(route('password.phone.status'))
            ->assertOk()
            ->assertJson(['verified' => true]);
    }

    public function test_a_wrong_callback_secret_is_not_found(): void
    {
        Http::fake(['api.verify.mn/*' => Http::response(['id' => 'sess_1'], 201)]);

        $this->user();
        $this->post(route('password.phone.send'), ['phone' => '99112233']);

        $this->postJson(route('verify.callback', ['secret' => 'buruu']), [
            'id' => 'sess_1',
        ])->assertNotFound();

        $this->assertNull(PhoneVerification::query()->firstOrFail()->verified_at);
    }

    public function test_an_unknown_phone_looks_the_same_but_sends_nothing(): void
    {
        Http::fake();

        $this->post(route('password.phone.send'), ['phone' => '88001122'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertNothingSent();

        // Мөр үүссэн ч кодыг нь хэн ч мэдэхгүй тул цаашид ахихгүй.
        $this->post(route('password.phone.confirm'), ['code' => '123456'])
            ->assertSessionHasErrors('code');
    }

    public function test_an_expired_code_cannot_be_used(): void
    {
        Http::fake(['api.verify.mn/*' => Http::response(['id' => 'sess_9'], 201)]);

        $this->user();
        $this->post(route('password.phone.send'), ['phone' => '99112233']);

        $record = PhoneVerification::query()->firstOrFail();
        $code = $this->codeFor($record);
        $record->update(['expires_at' => Carbon::now()->subMinute()]);

        $this->post(route('password.phone.confirm'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->get(route('password.request'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('phoneState.step', 'phone'));
    }

    public function test_the_password_cannot_be_changed_before_the_code_is_confirmed(): void
    {
        Http::fake(['api.verify.mn/*' => Http::response(['id' => 'sess_5'], 201)]);

        $user = $this->user();
        $this->post(route('password.phone.send'), ['phone' => '99112233']);

        $this->post(route('password.phone.update'), [
            'password' => 'ShineNuuts1',
            'password_confirmation' => 'ShineNuuts1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('HuuchinNuuts1', $user->fresh()->password));
    }

    /** Илгээсэн кодыг verify.mn руу явуулсан хүсэлтээс нь уншина. */
    private function codeFor(PhoneVerification $record): string
    {
        $code = null;

        Http::assertSent(function ($request) use (&$code) {
            if (str_contains($request->url(), '/sessions')) {
                $code = (string) $request['text'];
            }

            return true;
        });

        $this->assertNotNull($code);
        $this->assertTrue(Hash::check($code, $record->code_hash));

        return $code;
    }
}
