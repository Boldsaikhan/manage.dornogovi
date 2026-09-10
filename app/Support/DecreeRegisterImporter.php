<?php

namespace App\Support;

use App\Models\Decree;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Захирамж, тушаалын бүртгэлийг Excel/Word файлаас оруулна.
 *
 * Цаасан бүртгэлийг гараар хуулах нь алдаа гаргадаг тул эх файлаас нь
 * шууд уншина. Толгойг таньж багануудыг өөрөө тааруулах бөгөөд хэрэглэгч
 * оруулахын өмнө нүдээрээ шалгаж, шаардвал өөрчилнө.
 */
class DecreeRegisterImporter
{
    /** Оруулж болох талбарууд. */
    public const FIELDS = [
        'number' => 'Бүртгэлийн дугаар',
        'issued_on' => 'Батлагдсан огноо',
        'title' => 'Тэргүү / гарчиг',
        'page_count' => 'Хуудасны тоо',
        'effective_on' => 'Дагаж мөрдөх огноо',
        'attachment_name' => 'Хавсралтын нэр',
        'attachment_pages' => 'Хавсралтын хуудас',
        'original_form' => 'Эх хувийн шинж',
        'file_index' => 'Хэргийн индекс',
        'person_name' => 'Боловсруулсан',
    ];

    /** Толгойн нэршлийн хувилбарууд. */
    private const SYNONYMS = [
        'number' => ['дугаар', 'бүртгэлийндугаар', 'регистр'],
        'issued_on' => ['огноо', 'батлагдсаногноо', 'батлагдсанонсарөдөр'],
        'title' => ['тэргүү', 'гарчиг', 'захирамжийнтэргүү', 'тушаалынгарчиг', 'захирамжийнгарчиг', 'тушаалынтэргүү', 'агуулга'],
        'page_count' => ['хуудаснытоо', 'хуудас'],
        'effective_on' => ['дагажмөрдөхогноо', 'дагажмөрдөх', 'дагажмөрдөхонсарөдөр'],
        'attachment_name' => ['баримтбичгийннэр', 'хавсралтыннэр', 'хавсралтынмэдээлэл', 'баримтбичгийннэрагуулгабүрдэл'],
        'attachment_pages' => ['хавсралтынхуудас', 'хавсралтхуудаснытоо'],
        'original_form' => ['эххувийншинж', 'баримтбичгийнэххувийншинж'],
        'file_index' => ['хэргийниндекс', 'хххнжынхэргийниндекс', 'индекс'],
        'person_name' => ['боловсруулсан', 'боловсруулсаналбантушаалтан', 'албантушаалтан', 'боловсруулагч'],
    ];

    /** Тоон талбарууд. */
    private const NUMERIC = ['page_count', 'attachment_pages'];

    /** Огнооны талбарууд. */
    private const DATES = ['issued_on', 'effective_on'];

    /**
     * Файлын мөрүүдээс толгой болон өгөгдлийг ялгана.
     *
     * @param  array<int, array<int, string>>  $rows
     * @return array{headers: list<string>, mapping: array<string, int|null>, rows: array<int, array<int, string>>}
     */
    public function analyse(array $rows): array
    {
        $rows = array_values(array_filter(
            $rows,
            fn (array $row) => trim(implode('', $row)) !== '',
        ));

        $headerIndex = $this->findHeaderRow($rows);
        $headers = $headerIndex === null ? [] : array_map('trim', $rows[$headerIndex]);

        $body = $headerIndex === null
            ? $rows
            : array_values(array_slice($rows, $headerIndex + 1));

        // Толгойн доор «1 2 3 4…» гэсэн дугаарын мөр байвал алгасна.
        if ($body !== [] && $this->looksLikeNumberRow($body[0])) {
            array_shift($body);
        }

        return [
            'headers' => $headers,
            'mapping' => $this->guessMapping($headers),
            'rows' => $body,
        ];
    }

    /**
     * Толгойн нэрсээс багануудыг таана.
     *
     * @param  list<string>  $headers
     * @return array<string, int|null>
     */
    public function guessMapping(array $headers): array
    {
        $normalized = array_map(fn ($h) => $this->normalize((string) $h), $headers);
        $mapping = [];
        $used = [];

        foreach (array_keys(self::FIELDS) as $field) {
            $candidates = array_merge(
                [$this->normalize(self::FIELDS[$field])],
                self::SYNONYMS[$field] ?? [],
            );

            $found = null;

            foreach ($candidates as $candidate) {
                foreach ($normalized as $index => $header) {
                    if ($header === '' || in_array($index, $used, true)) {
                        continue;
                    }

                    if ($header === $candidate || str_contains($header, $candidate)) {
                        $found = $index;
                        break 2;
                    }
                }
            }

            if ($found !== null) {
                $used[] = $found;
            }

            $mapping[$field] = $found;
        }

        return $mapping;
    }

    /**
     * Тааруулсан баганаар мөрүүдийг бэлтгэнэ (хадгалахгүй — урьдчилан харуулна).
     *
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string, int|null>  $mapping
     * @return list<array<string, mixed>>
     */
    public function build(array $rows, array $mapping): array
    {
        $out = [];

        foreach ($rows as $row) {
            $entry = [];

            foreach (array_keys(self::FIELDS) as $field) {
                $index = $mapping[$field] ?? null;
                $raw = $index === null ? '' : trim((string) ($row[$index] ?? ''));

                $entry[$field] = match (true) {
                    in_array($field, self::DATES, true) => $this->date($raw),
                    in_array($field, self::NUMERIC, true) => $this->integer($raw),
                    $field === 'number' => $this->number($raw),
                    default => $raw !== '' ? $raw : null,
                };
            }

            // Дугаар ч, гарчиг ч байхгүй мөрийг алгасна.
            if (($entry['number'] ?? null) === null && ($entry['title'] ?? null) === null) {
                continue;
            }

            $out[] = $entry;
        }

        return $out;
    }

    /**
     * Бэлтгэсэн мөрүүдийг хадгална.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array{created: int, skipped: int, missing_number: int}
     */
    public function store(string $kind, string $category, array $entries): array
    {
        $existing = Decree::query()
            ->where('kind', $kind)
            ->pluck('number')
            ->map(fn (?string $number) => $this->compareNumber((string) $number))
            ->filter()
            ->all();

        $created = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($entries as $entry) {
            $number = $entry['number'] ?? null;

            if ($number === null) {
                $missing++;

                continue;
            }

            if (in_array($this->compareNumber($number), $existing, true)) {
                $skipped++;

                continue;
            }

            Decree::query()->create([
                'category' => $category,
                'kind' => $kind,
                'number' => $number,
                'title' => $entry['title'] ?? '',
                'issued_on' => $entry['issued_on'] ?? null,
                'page_count' => $entry['page_count'] ?? null,
                'effective_on' => $entry['effective_on'] ?? null,
                'attachment_name' => $entry['attachment_name'] ?? null,
                'attachment_pages' => $entry['attachment_pages'] ?? null,
                'original_form' => $entry['original_form'] ?? null,
                'file_index' => $entry['file_index'] ?? null,
                'person_name' => $entry['person_name'] ?? null,
            ]);

            $existing[] = $this->compareNumber($number);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'missing_number' => $missing];
    }

    /** «А/07», «A/7», «07» → «07» */
    public function number(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        // Нэг оронтой дугаарыг «07» хэлбэрт оруулна (цаасан бүртгэлийн хэлбэр).
        return strlen($digits) === 1 ? '0'.$digits : $digits;
    }

    /** Харьцуулах хэлбэр — эхний тэгүүдийг үл тооно. */
    private function compareNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        return ltrim($digits, '0') ?: ($digits === '' ? '' : '0');
    }

    /** «2026.01.02», «2026-01-02», «02/01/2026», Excel-ийн серийн дугаар. */
    public function date(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        // Excel-ийн огноо — 1899-12-30-аас хойших өдрийн тоо.
        if (preg_match('/^\d{5}(\.\d+)?$/', $raw)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $raw)->format('Y-m-d');
        }

        if (preg_match('/(\d{4})\D(\d{1,2})\D(\d{1,2})/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        if (preg_match('/(\d{1,2})\D(\d{1,2})\D(\d{4})/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function integer(string $raw): ?int
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits === '' ? null : (int) $digits;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function findHeaderRow(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $normalized = implode(' ', array_map(fn ($c) => $this->normalize((string) $c), $row));

            if (str_contains($normalized, 'дугаар') && (
                str_contains($normalized, 'огноо') || str_contains($normalized, 'тэргүү')
                || str_contains($normalized, 'гарчиг')
            )) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $row
     */
    private function looksLikeNumberRow(array $row): bool
    {
        $values = array_filter(array_map('trim', $row), fn ($v) => $v !== '');

        if (count($values) < 3) {
            return false;
        }

        foreach ($values as $value) {
            if (! preg_match('/^\d{1,2}$/', $value)) {
                return false;
            }
        }

        return true;
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim($value))) ?: '';
    }
}
