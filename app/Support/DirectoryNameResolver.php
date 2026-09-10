<?php

namespace App\Support;

use App\Models\PhoneDirectoryEntry;

/**
 * Цаасан бүртгэл дэх нэрийг утасны жагсаалтын нэртэй тааруулна.
 *
 * Үсгийн зөрүүг (ө/о, ү/у, ё/е, й/и) үл тооно, овгийн үсэггүйгээр ч хайна.
 * Жагсаалтад олдоогүй нэрийг цаасан дээрх хэвээр нь үлдээнэ.
 */
class DirectoryNameResolver
{
    /**
     * Утасны жагсаалтын нэрс: [харьцуулах түлхүүр => жинхэнэ бичлэг]
     *
     * @return array<string, string>
     */
    public static function directory(): array
    {
        $names = [];

        PhoneDirectoryEntry::query()
            ->get(['person_name'])
            ->each(function (PhoneDirectoryEntry $entry) use (&$names): void {
                $short = PersonName::short((string) $entry->person_name);

                if ($short === '') {
                    return;
                }

                $names[self::key($short)] = $short;

                $given = self::givenName($short);

                if ($given !== '' && ! isset($names[$given])) {
                    $names[$given] = $short;
                }
            });

        return $names;
    }

    /**
     * @param  array<string, string>  $directory
     */
    public static function resolve(?string $person, array $directory): ?string
    {
        $person = trim((string) $person);

        if ($person === '') {
            return null;
        }

        $key = self::key($person);

        if (isset($directory[$key])) {
            return $directory[$key];
        }

        $given = self::givenName($person);

        if ($given !== '' && isset($directory[$given])) {
            return $directory[$given];
        }

        return $person;
    }

    /** Харьцуулах түлхүүр — үсгийн зөрүүг үл тооно. */
    public static function key(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return str_replace(
            ['ө', 'ү', 'ё', 'й', 'ъ', 'ь', ' ', '-'],
            ['о', 'у', 'е', 'и', '', '', '', ''],
            $value,
        );
    }

    /** «Ц.Энхтунгалаг» → «энхтунгалаг» */
    public static function givenName(string $value): string
    {
        $parts = explode('.', self::key($value));

        return trim(end($parts));
    }
}
