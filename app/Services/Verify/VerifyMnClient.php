<?php

namespace App\Services\Verify;

use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * verify.mn — нэг удаагийн кодын session үүсгэнэ.
 *
 * POST {base_url}/sessions
 *   Authorization: Bearer <API KEY>
 *   { phone, text, callback, responseSms }
 *
 * Хэрэглэгч кодоо хуудсан дээр бичиж болно, эсвэл verify.mn нь баталгаажуулаад
 * бидний callback руу мэдэгдэнэ — хоёулаа адилхан ажиллана.
 *
 * verify.mn унтраалттай эсвэл алдаа өгвөл хуучин SMS сувгаар (SmsSender) кодыг
 * илгээж, урсгал тасрахгүй.
 */
class VerifyMnClient
{
    public function __construct(private SmsSender $sms) {}

    public function isEnabled(): bool
    {
        return (bool) config('verify.enabled') && trim((string) config('verify.api_key')) !== '';
    }

    /**
     * Session үүсгэж, кодыг хэрэглэгч рүү хүргэнэ.
     *
     * @param  string  $phone  8 оронтой дугаар.
     * @return array{sent: bool, session_id: ?string, channel: string}
     */
    public function sendCode(string $phone, string $code): array
    {
        if ($this->isEnabled()) {
            $sessionId = $this->createSession($phone, $code);

            if ($sessionId !== false) {
                return ['sent' => true, 'session_id' => $sessionId, 'channel' => 'verify.mn'];
            }
        }

        // Нөөц суваг — тохируулсан SMS API (хөгжүүлэлтэд log).
        $sent = $this->sms->send($phone, $this->message($code));

        return ['sent' => $sent, 'session_id' => null, 'channel' => 'sms'];
    }

    /**
     * @return string|null|false  session id, эсвэл null (буцаагаагүй), false (алдаа).
     */
    private function createSession(string $phone, string $code): string|null|false
    {
        try {
            $response = Http::timeout((int) config('verify.timeout', 15))
                ->withToken(trim((string) config('verify.api_key')))
                ->acceptJson()
                ->post(config('verify.base_url').'/sessions', array_filter([
                    'phone' => $phone,
                    'text' => $code,
                    'callback' => $this->callbackUrl(),
                    'responseSms' => trim((string) config('verify.response_sms')) ?: null,
                ]));

            if (! $response->successful()) {
                Log::warning('verify.mn: session үүсгэж чадсангүй.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $id = $response->json('id')
                ?? $response->json('sessionId')
                ?? $response->json('data.id');

            return $id === null ? null : (string) $id;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** verify.mn руу өгөх callback хаяг. Нууц түлхүүр нь хаягтаа шингэсэн. */
    public function callbackUrl(): ?string
    {
        $secret = trim((string) config('verify.callback_secret'));

        if ($secret === '') {
            return null;
        }

        return route('verify.callback', ['secret' => $secret]);
    }

    public function message(string $code): string
    {
        return str_replace(
            ['{code}', '{minutes}', '{app}'],
            [$code, (string) config('verify.code_ttl', 10), (string) config('app.name')],
            (string) config('verify.code_message'),
        );
    }

    /** Тохируулсан урттай нэг удаагийн тоон код. */
    public function generateCode(): string
    {
        $length = max(4, min(8, (int) config('verify.code_length', 6)));
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }
}
