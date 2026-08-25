<?php

namespace App\Services\Ai;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;

class AiSettings
{
    public const KEY_PROVIDER = 'ai.provider';

    public const KEY_OPENAI_API_KEY = 'ai.openai_api_key';

    public const KEY_OPENAI_MODEL = 'ai.openai_model';

    public const KEY_DAILY_LIMIT = 'ai.daily_question_limit';

    public const KEY_ENABLED = 'ai.enabled';

    public function get(string $key, ?string $default = null): ?string
    {
        $value = Cache::remember("app_setting:{$key}", 60, function () use ($key) {
            return AppSetting::query()->where('key', $key)->value('value');
        });

        return $value ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("app_setting:{$key}");
    }

    public function enabled(): bool
    {
        return ($this->get(self::KEY_ENABLED, '1') ?? '1') !== '0';
    }

    public function provider(): string
    {
        $provider = $this->get(self::KEY_PROVIDER, config('ai.default_provider', 'local'));

        return in_array($provider, ['openai', 'local'], true) ? $provider : 'local';
    }

    public function openaiModel(): string
    {
        return $this->get(self::KEY_OPENAI_MODEL, 'gpt-4o-mini') ?: 'gpt-4o-mini';
    }

    public function dailyQuestionLimit(): int
    {
        return max(0, (int) ($this->get(self::KEY_DAILY_LIMIT, '30') ?? 30));
    }

    public function hasOpenAiKey(): bool
    {
        return filled($this->openaiApiKey());
    }

    public function openaiApiKey(): ?string
    {
        $stored = $this->get(self::KEY_OPENAI_API_KEY);
        if (! filled($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setOpenAiApiKey(?string $plain): void
    {
        if ($plain === null || trim($plain) === '') {
            $this->set(self::KEY_OPENAI_API_KEY, null);

            return;
        }

        $this->set(self::KEY_OPENAI_API_KEY, Crypt::encryptString(trim($plain)));
    }

    /**
     * Admin UI-д илгээх (түлхүүрийг бүрэн ил гаргахгүй).
     *
     * @return array<string, mixed>
     */
    public function forAdmin(): array
    {
        $key = $this->openaiApiKey();

        return [
            'enabled' => $this->enabled(),
            'provider' => $this->provider(),
            'openai_model' => $this->openaiModel(),
            'daily_question_limit' => $this->dailyQuestionLimit(),
            'has_api_key' => filled($key),
            'api_key_hint' => filled($key) ? $this->maskKey($key) : null,
        ];
    }

    private function maskKey(string $key): string
    {
        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($key, 0, 3).str_repeat('*', max(4, $len - 7)).substr($key, -4);
    }
}
