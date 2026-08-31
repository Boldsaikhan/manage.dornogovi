<?php

namespace App\Support;

class ReportsData
{
    public static function path(string $reportKey): string
    {
        return database_path("data/reports/{$reportKey}.json");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rows(string $reportKey): array
    {
        $path = self::path($reportKey);

        if (! is_file($path)) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function meta(string $reportKey): ?array
    {
        $path = self::path($reportKey);

        if (! is_file($path)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            return null;
        }

        unset($payload['rows']);

        return $payload;
    }
}
