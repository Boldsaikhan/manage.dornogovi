<?php

namespace App\Support;

use App\Http\Controllers\DesktopAppController;
use App\Models\AppSetting;

/**
 * Ширээний програмын хувилбарын тохиргоо.
 *
 * Админ суулгацаа байршуулаад хувилбарын дугаарыг нь энд бичнэ.
 * Суусан програм энэ мэдээллийг уншиж, шинэ хувилбар гарсныг мэдэгдэнэ.
 */
class DesktopRelease
{
    private const KEY = 'desktop.release';

    /**
     * @return array{version: string, force: bool, notes: string}
     */
    public static function all(): array
    {
        try {
            $raw = AppSetting::query()->where('key', self::KEY)->value('value');
        } catch (\Throwable $e) {
            // Хүснэгт бэлэн биш (migration-аас өмнө).
            $raw = null;
        }

        $saved = $raw ? json_decode((string) $raw, true) : null;
        $saved = is_array($saved) ? $saved : [];

        return [
            'version' => (string) ($saved['version'] ?? ''),
            'force' => (bool) ($saved['force'] ?? false),
            'notes' => (string) ($saved['notes'] ?? ''),
        ];
    }

    /**
     * @param  array{version?: string, force?: bool, notes?: string}  $values
     */
    public static function save(array $values): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => json_encode([
                'version' => trim((string) ($values['version'] ?? '')),
                'force' => (bool) ($values['force'] ?? false),
                'notes' => trim((string) ($values['notes'] ?? '')),
            ], JSON_UNESCAPED_UNICODE)],
        );
    }

    /**
     * Суусан програмд өгөх мэдээлэл.
     *
     * @return array{version: string, force: bool, notes: string, url: string, size: int|null, available: bool}
     */
    public static function manifest(): array
    {
        $status = DesktopAppController::status();
        $release = self::all();

        /*
         * Хувилбарын дугаарыг гараар бичдэг тул байршуулсан суулгацтайгаа
         * зөрөх эрсдэлтэй — тэгвэл програм шинэчлэлт татаад хуучин
         * хувилбараа л дахин суулгаад төгсгөлгүй давтана.
         *
         * Кодтой хамт ирсэн суулгацыг түгээж байгаа бол хувилбарыг нь
         * build-ийн бичсэн version.json-оос шууд авна.
         */
        if (DesktopAppController::servingBundled()) {
            $bundled = DesktopAppController::bundledVersion();

            if ($bundled !== '') {
                $release['version'] = $bundled;
            }
        }

        return [
            ...$release,
            'url' => route('desktop.download'),
            'size' => $status['size'],
            'available' => $status['available'],
        ];
    }
}
