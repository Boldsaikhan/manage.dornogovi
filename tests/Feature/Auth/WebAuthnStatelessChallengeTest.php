<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Гар утасны PWA дээр биометрик цонх хариу авахад хэдэн секунд зарцуулдаг —
 * энэ хугацаанд session cookie алга болвол (апп дэвсгэрт очиход OS хөтчийг
 * хааж, дараа нь дахин нээвэл) "Нэвтрэх сесс дууссан" алдаа мөнхөд гардаг
 * байсан. Challenge-ийг session-оос гадна шифрлэсэн, бие даасан токеноор ч
 * дамжуулдаг болгосон тул session алдагдсан ч баталгаажина.
 */
class WebAuthnStatelessChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_responses_include_a_stateless_token(): void
    {
        $user = User::factory()->create();

        WebAuthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => 'dGVzdC1jcmVkZW50aWFs',
            'public_key' => 'test-key',
            'sign_count' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('webauthn.verify.options'))
            ->assertOk()
            ->assertJsonStructure(['publicKey', 'state']);

        $this->actingAs($user)
            ->postJson(route('webauthn.register.options'))
            ->assertOk()
            ->assertJsonStructure(['publicKey', 'state']);
    }

    public function test_unlock_still_reads_the_challenge_after_the_session_is_lost(): void
    {
        $user = User::factory()->create();

        WebAuthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => 'dGVzdC1jcmVkZW50aWFs',
            'public_key' => 'test-key',
            'sign_count' => 0,
        ]);

        $options = $this->actingAs($user)
            ->postJson(route('webauthn.verify.options'))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($options['state'] ?? null);

        // Гар утсан дээр апп дэвсгэрт очиж, session cookie алга болсныг дуурайна.
        $this->app['session.store']->flush();

        $response = $this->actingAs($user)->postJson(route('app.unlock'), [
            'assertion' => [
                'id' => 'notarealcredential',
                'clientDataJSON' => 'x',
                'authenticatorData' => 'x',
                'signature' => 'x',
                'state' => $options['state'],
            ],
        ]);

        // «Нэвтрэх сесс дууссан» биш («state» токен ажилласан), харин
        // тухайн credential бүртгэлгүй тул өөр алдаа гарна.
        $response->assertStatus(422);
        $this->assertStringContainsString(
            'бүртгэгдээгүй',
            $response->json('errors.webauthn.0'),
        );
    }

    public function test_unlock_still_fails_with_the_session_message_when_there_is_no_state_and_no_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('app.unlock'), [
            'assertion' => [
                'id' => 'notarealcredential',
                'clientDataJSON' => 'x',
                'authenticatorData' => 'x',
                'signature' => 'x',
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'Нэвтрэх сесс дууссан',
            $response->json('errors.webauthn.0'),
        );
    }
}
