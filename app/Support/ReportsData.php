<?php

namespace App\Support;

class ReportsData
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rows(string $reportKey): array
    {
        $path = storage_path("app/reports/{$reportKey}.json");

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
        $path = storage_path("app/reports/{$reportKey}.json");

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
