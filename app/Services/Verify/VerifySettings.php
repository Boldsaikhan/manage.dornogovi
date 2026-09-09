<?php

namespace App\Services\Verify;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * verify.mn-ийн тохиргоо — «Системийн тохиргоо» хуудсаас удирдана.
 *
 * Утгыг өгөгдлийн санд хадгална (API түлхүүр нь шифрлэгдэнэ). Санд утга
 * байхгүй бол .env / config-оос авна — хуучин тохиргоотой серверүүд хэвээр
 * ажиллана.
 */
class VerifySettings
{
    public const KEY_ENABLED = 'verify.enabled';

    public const KEY_API_KEY = 'verify.api_key';

    public const KEY_CALLBACK_SECRET = 'verify.callback_secret';

    public const KEY_RESPONSE_SMS = 'verify.response_sms';

    public const KEY_SHORTCODE = 'verify.shortcode';

    public function get(string $key): ?string
    {
        return Cache::remember(
            "app_setting:{$key}",
            60,
            fn () => AppSetting::query()->where('key', $key)->value('value'),
        );
    }

    public function set(string $key, ?string $value): void
    {
        AppSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget("app_setting:{$key}");
    }

    public function enabled(): bool
    {
        $stored = $this->get(self::KEY_ENABLED);

        if ($stored === null) {
            return (bool) config('verify.enabled');
        }

        return $stored === '1';
    }

    public function apiKey(): ?string
    {
        $stored = $this->get(self::KEY_API_KEY);

        if (! filled($stored)) {
            return trim((string) config('verify.api_key')) ?: null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    public function setApiKey(?string $plain): void
    {
        $plain = trim((string) $plain);

        $this->set(self::KEY_API_KEY, $plain === '' ? null : Crypt::encryptString($plain));
    }

    /**
     * Callback хаягийн нууц түлхүүр.
     *
     * Байхгүй бол өөрөө үүсгэнэ — админ гараар бодох шаардлагагүй.
     */
    public function callbackSecret(): string
    {
        $stored = trim((string) $this->get(self::KEY_CALLBACK_SECRET));

        if ($stored !== '') {
            return $stored;
        }

        $fromEnv = trim((string) config('verify.callback_secret'));

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $generated = Str::random(48);
        $this->set(self::KEY_CALLBACK_SECRET, $generated);

        return $generated;
    }

    public function regenerateCallbackSecret(): string
    {
        $generated = Str::random(48);
        $this->set(self::KEY_CALLBACK_SECRET, $generated);

        return $generated;
    }

    public function shortcode(): string
    {
        $stored = trim((string) $this->get(self::KEY_SHORTCODE));

        return $stored !== '' ? $stored : (string) config('verify.shortcode', '144773');
    }

    public function responseSms(): string
    {
        $stored = $this->get(self::KEY_RESPONSE_SMS);

        if ($stored === null) {
            return (string) config('verify.response_sms');
        }

        return trim($stored);
    }

    public function isConfigured(): bool
    {
        return $this->enabled() && filled($this->apiKey());
    }

    /**
     * Админ хуудсанд илгээх мэдээлэл — түлхүүрийг бүтнээр нь гаргахгүй.
     *
     * @return array<string, mixed>
     */
    public function forAdmin(): array
    {
        $key = $this->apiKey();

        return [
            'enabled' => $this->enabled(),
            'has_api_key' => filled($key),
            'api_key_hint' => $key ? Str::limit($key, 12, '…') : null,
            'shortcode' => $this->shortcode(),
            'response_sms' => $this->responseSms(),
            'callback_url' => route('verify.callback', ['secret' => $this->callbackSecret()]),
            'sms_cost' => (int) config('verify.sms_cost', 150),
            'code_ttl' => (int) config('verify.code_ttl', 5),
            'configured' => $this->isConfigured(),
        ];
    }
}
