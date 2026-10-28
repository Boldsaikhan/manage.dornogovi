<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Нэвтрэх мэдээллийн сангийн ерөнхий тохиргоо.
 *
 * Автоматаар сунгах нь асаалттай бол админаас нээж өгсөн хугацаа нь тухайн
 * албан хаагч системийг ашиглаж байх хугацаанд өөрөө сунгагдана.
 */
class VaultSettings
{
    private const KEY = 'vault.settings';

    /**
     * @return array{auto_extend: bool}
     */
    public static function all(): array
    {
        try {
            $raw = AppSetting::query()->where('key', self::KEY)->value('value');
        } catch (\Throwable) {
            // Хүснэгт бэлэн биш (migration-аас өмнө).
            $raw = null;
        }

        $saved = $raw ? json_decode((string) $raw, true) : null;
        $saved = is_array($saved) ? $saved : [];

        return [
            // Анхдагчаар асаалттай — админ гараар сунгаж суухгүйн тулд.
            'auto_extend' => (bool) ($saved['auto_extend'] ?? true),
        ];
    }

    public static function autoExtend(): bool
    {
        return self::all()['auto_extend'];
    }

    /**
     * @param  array{auto_extend?: bool}  $values
     */
    public static function save(array $values): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => json_encode([
                'auto_extend' => (bool) ($values['auto_extend'] ?? false),
            ], JSON_UNESCAPED_UNICODE)],
        );
    }
}
