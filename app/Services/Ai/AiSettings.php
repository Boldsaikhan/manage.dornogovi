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

    public const KEY_DISPLAY_NAME = 'ai.display_name';

    public const DEFAULT_DISPLAY_NAME = 'Manage AI';

    public const KEY_MODULE_ACCESS = 'ai.module_access';

    /** Хандалтын түвшин. */
    public const ACCESS_NONE = 'none';

    public const ACCESS_READ = 'read';

    public const ACCESS_WRITE = 'write';

    public const ACCESS_LABELS = [
        self::ACCESS_NONE => 'Хаалттай',
        self::ACCESS_READ => 'Зөвхөн харах',
        self::ACCESS_WRITE => 'Харах + бүртгэл үүсгэх',
    ];

    /** Модульд хамаарахгүй ерөнхий хэрэгслүүд (самбар, ажилтны хайлт). */
    public const GENERAL_MODULE = 'general';

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

    public function displayName(): string
    {
        $name = trim((string) ($this->get(self::KEY_DISPLAY_NAME, self::DEFAULT_DISPLAY_NAME) ?? ''));

        return $name !== '' ? $name : self::DEFAULT_DISPLAY_NAME;
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
    /**
     * AI аль цэсэд ямар эрхтэй болох тохиргоо.
     *
     * @return array<string, string>
     */
    public function moduleAccess(): array
    {
        $raw = $this->get(self::KEY_MODULE_ACCESS);
        $decoded = $raw ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, string>  $access
     */
    public function setModuleAccess(array $access): void
    {
        $clean = [];

        foreach ($access as $module => $level) {
            if (! is_string($module) || $module === '') {
                continue;
            }

            $clean[$module] = in_array($level, array_keys(self::ACCESS_LABELS), true)
                ? $level
                : self::ACCESS_READ;
        }

        $this->set(self::KEY_MODULE_ACCESS, json_encode($clean, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Тохируулаагүй бол анхдагчаар зөвхөн харна.
     */
    public function accessFor(string $module): string
    {
        return $this->moduleAccess()[$module] ?? self::ACCESS_READ;
    }

    public function canRead(string $module): bool
    {
        return $this->accessFor($module) !== self::ACCESS_NONE;
    }

    public function canWrite(string $module): bool
    {
        return $this->accessFor($module) === self::ACCESS_WRITE;
    }

    public function forAdmin(): array
    {
        $key = $this->openaiApiKey();

        return [
            'enabled' => $this->enabled(),
            'display_name' => $this->displayName(),
            'provider' => $this->provider(),
            'openai_model' => $this->openaiModel(),
            'daily_question_limit' => $this->dailyQuestionLimit(),
            'has_api_key' => filled($key),
            'api_key_hint' => filled($key) ? $this->maskKey($key) : null,
            'module_access' => $this->moduleAccess(),
            'access_labels' => self::ACCESS_LABELS,
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
