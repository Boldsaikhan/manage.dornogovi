<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Сервер дээрх сүүлийн алдаануудыг уншина.
 *
 * Зөвхөн `storage/logs/laravel.log`-ийн сүүл хэсгийг уншиж, ERROR болон
 * түүнээс дээш түвшний бичлэгийг задална. Файл томрох тусам бүтнээр нь
 * уншихгүй — сүүлийн хэдэн зуун килобайт л хангалттай.
 */
class SystemLog
{
    /** Сүүлээс нь хэдэн байт уншихыг хязгаарлана. */
    private const TAIL_BYTES = 400_000;

    private const LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    public static function path(): string
    {
        return storage_path('logs/laravel.log');
    }

    /**
     * @return list<array{time: string, level: string, message: string, trace: string}>
     */
    public static function recent(int $limit = 40): array
    {
        $path = self::path();

        if (! is_readable($path)) {
            return [];
        }

        $raw = self::tail($path);

        // Бичлэг бүр «[огноо] орчин.ТҮВШИН: мэдээлэл» гэж эхэлнэ.
        $parts = preg_split(
            '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\]\s*([a-z0-9_.-]+)\.([A-Z]+):\s*/m',
            $raw,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );

        if (! is_array($parts)) {
            return [];
        }

        $entries = [];

        // [0] нь эхний бичлэгийн өмнөх үлдэгдэл — алгасна.
        for ($i = 1; $i + 3 <= count($parts); $i += 4) {
            $level = strtoupper((string) $parts[$i + 2]);

            if (! in_array($level, self::LEVELS, true)) {
                continue;
            }

            $body = (string) ($parts[$i + 3] ?? '');
            [$message, $trace] = self::splitBody($body);

            $entries[] = [
                'time' => self::formatTime((string) $parts[$i]),
                'level' => $level,
                'message' => $message,
                'trace' => $trace,
            ];
        }

        return array_slice(array_reverse($entries), 0, max(1, $limit));
    }

    private static function tail(string $path): string
    {
        $size = (int) @filesize($path);

        if ($size <= self::TAIL_BYTES) {
            return (string) @file_get_contents($path);
        }

        $handle = @fopen($path, 'rb');

        if (! $handle) {
            return '';
        }

        fseek($handle, -self::TAIL_BYTES, SEEK_END);
        $raw = (string) fread($handle, self::TAIL_BYTES);
        fclose($handle);

        return $raw;
    }

    /** @return array{0: string, 1: string} */
    private static function splitBody(string $body): array
    {
        $body = rtrim($body);
        $lines = preg_split('/\r?\n/', $body) ?: [];

        $message = trim((string) array_shift($lines));
        $trace = trim(implode("\n", $lines));

        return [
            mb_substr($message, 0, 2000),
            mb_substr($trace, 0, 8000),
        ];
    }

    private static function formatTime(string $value): string
    {
        try {
            return Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $value;
        }
    }
}
