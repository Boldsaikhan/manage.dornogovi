<?php

namespace App\Services\Verify;

use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * verify.mn — Mobile-Originated (MO) SMS баталгаажуулалт.
 *
 * Бид хэрэглэгч рүү SMS илгээхгүй. Хэрэглэгч өөрөө 144773 дугаар руу
 * нэг удаагийн кодоо илгээж, verify.mn түүнийг таниулна.
 *
 *   POST /sessions          → sessionId, smsUri, displayInstruction, expiresAt
 *   GET  /sessions/{id}     → sessionStatus: PENDING | VERIFIED | EXPIRED
 *
 * Callback нь зөвхөн «одоо шалгаарай» гэсэн дохио (бие, гарын үсэг байхгүй)
 * тул түүнд дангаар нь итгэхгүй — үргэлж төлвийг нь дахин асууна.
 */
class VerifyMnClient
{
    public function __construct(
        private SmsSender $sms,
        private VerifySettings $settings,
    ) {}

    public function isEnabled(): bool
    {
        return $this->settings->isConfigured();
    }

    /**
     * Баталгаажуулалтын session үүсгэнэ.
     *
     * @param  string  $phone  8 оронтой дугаар.
     * @return array{
     *     channel: string,
     *     sent: bool,
     *     session_id: ?string,
     *     sms_uri: ?string,
     *     instruction: ?string,
     *     expires_at: ?string
     * }
     */
    public function startVerification(string $phone, string $code): array
    {
        if ($this->isEnabled()) {
            $session = $this->createSession($phone, $code);

            if ($session !== null) {
                return [
                    'channel' => 'verify.mn',
                    'sent' => true,
                    'session_id' => $session['sessionId'] ?? null,
                    'sms_uri' => $session['smsUri'] ?? $this->fallbackSmsUri($code),
                    'instruction' => $session['displayInstruction'] ?? null,
                    'expires_at' => $session['expiresAt'] ?? null,
                ];
            }
        }

        // Нөөц суваг — тохируулсан SMS API (хөгжүүлэлтэд log).
        // Энэ тохиолдолд кодыг хэрэглэгч рүү бид илгээж, тэр гараар бичнэ.
        $sent = $this->sms->send($phone, $this->message($code));

        return [
            'channel' => 'sms',
            'sent' => $sent,
            'session_id' => null,
            'sms_uri' => null,
            'instruction' => null,
            'expires_at' => null,
        ];
    }

    /**
     * Session-ы АЛБАН ЁСНЫ төлөв. Зөвхөн энэ VERIFIED бол баталгаажсан.
     *
     * @return string|null  PENDING | VERIFIED | EXPIRED, эсвэл асууж чадаагүй бол null.
     */
    public function sessionStatus(string $sessionId): ?string
    {
        try {
            $response = Http::timeout((int) config('verify.timeout', 15))
                ->acceptJson()
                ->get(config('verify.base_url').'/sessions/'.rawurlencode($sessionId));

            if (! $response->successful()) {
                return null;
            }

            $status = $response->json('sessionStatus');

            return is_string($status) ? strtoupper($status) : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function createSession(string $phone, string $code): ?array
    {
        try {
            $response = Http::timeout((int) config('verify.timeout', 15))
                ->withToken((string) $this->settings->apiKey())
                ->acceptJson()
                ->post(config('verify.base_url').'/sessions', array_filter([
                    'phone' => $phone,
                    'text' => $code,
                    'callback' => $this->callbackUrl(),
                    'responseSms' => $this->settings->responseSms() ?: null,
                ]));

            if ($response->successful()) {
                return (array) $response->json();
            }

            Log::warning('verify.mn: session үүсгэж чадсангүй.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** verify.mn руу өгөх callback хаяг. Нууц түлхүүр нь хаягтаа шингэсэн. */
    public function callbackUrl(): string
    {
        return route('verify.callback', ['secret' => $this->settings->callbackSecret()]);
    }

    /** verify.mn-ээс smsUri ирээгүй үед өөрсдөө угсарна. */
    public function fallbackSmsUri(string $code): string
    {
        return 'sms:'.$this->settings->shortcode().'?body='.rawurlencode($code);
    }

    /** Нөөц сувгаар (бид илгээх) явуулах бичвэр. */
    public function message(string $code): string
    {
        return sprintf(
            '%s — нууц үг сэргээх код: %s. Хугацаа %d минут.',
            (string) config('app.name'),
            $code,
            (int) config('verify.code_ttl', 5),
        );
    }

    /**
     * Нэг удаагийн тоон код.
     *
     * verify.mn нь ирсэн SMS-ийн текстийг ЯГ таарч байгаа эсэхээр шалгадаг тул
     * зөвхөн цифр ашиглана — хэрэглэгч андуурч бичих магадлал багасна.
     */
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
