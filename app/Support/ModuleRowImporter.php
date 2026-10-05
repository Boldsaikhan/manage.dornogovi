<?php

namespace App\Support;

/**
 * Хувийн импортлогчгүй (person_name-гүй) модулиудад зориулсан энгийн
 * Excel/Word импорт — тохиргооны `fields`-ийн шошгоор баганыг таамаглаж,
 * мөр бүрийг {талбар => утга} массив болгоно.
 *
 * @see AssignmentRegisterImporter Томилолтын бүртгэлийн тусгай (person_name
 *      суурьтай) хувилбар — энэ класс түүнийг орлохгүй, зэрэгцээ ажиллана.
 */
class ModuleRowImporter
{
    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, array<string, mixed>>  $fields
     * @return array{headers: list<string>, mapping: array<string, int|null>, rows: array<int, array<int, string>>}
     */
    public function analyse(array $rows, array $fields): array
    {
        $rows = array_values(array_filter(
            $rows,
            fn (array $row) => trim(implode('', $row)) !== '',
        ));

        $headerIndex = $this->findHeaderRow($rows, $fields);
        $headers = $headerIndex === null ? [] : array_map('trim', $rows[$headerIndex]);

        return [
            'headers' => $headers,
            'mapping' => $this->guessMapping($headers, $fields),
            'rows' => $headerIndex === null ? $rows : array_values(array_slice($rows, $headerIndex + 1)),
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, int|null>
     */
    public function guessMapping(array $headers, array $fields): array
    {
        $normalized = array_map(fn ($h) => $this->normalize((string) $h), $headers);
        $used = [];
        $mapping = [];

        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $candidate = $this->normalize((string) ($field['label'] ?? $name));

            $found = null;

            foreach ($normalized as $index => $header) {
                if ($header === '' || in_array($index, $used, true)) {
                    continue;
                }

                if ($header === $candidate || str_contains($header, $candidate) || str_contains($candidate, $header)) {
                    $found = $index;

                    break;
                }
            }

            if ($found !== null) {
                $used[] = $found;
            }

            $mapping[$name] = $found;
        }

        return $mapping;
    }

    /**
     * Тааруулалтын дагуу түүхий мөрүүдийг {талбар => утга} массив болгоно
     * (хадгалахгүй — зөвхөн бэлтгэнэ).
     *
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string, int|null>  $mapping
     * @param  array<int, array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    public function build(array $rows, array $mapping, array $fields): array
    {
        $primary = $fields[0]['name'] ?? null;
        $out = [];

        foreach ($rows as $row) {
            $entry = [];

            foreach ($fields as $field) {
                $name = (string) $field['name'];
                $index = $mapping[$name] ?? null;
                $raw = $index === null ? '' : trim((string) ($row[$index] ?? ''));

                $entry[$name] = $this->castValue($raw, $field);
            }

            if ($primary !== null && trim((string) ($entry[$primary] ?? '')) === '') {
                continue;
            }

            $out[] = $entry;
        }

        return $out;
    }

    /** @param  array<string, mixed>  $field */
    private function castValue(string $raw, array $field): mixed
    {
        if ($raw === '') {
            return null;
        }

        return match ($field['type'] ?? 'text') {
            'number' => $this->toNumber($raw),
            'select' => $this->matchOption($raw, $field['options'] ?? []),
            default => mb_substr($raw, 0, 5000),
        };
    }

    private function toNumber(string $raw): ?int
    {
        $normalized = str_replace(',', '.', $raw);

        return is_numeric($normalized) ? (int) round((float) $normalized) : null;
    }

    /** @param  array<string, string>  $options */
    private function matchOption(string $raw, array $options): ?string
    {
        $needle = $this->normalize($raw);

        foreach ($options as $key => $label) {
            if ($this->normalize((string) $label) === $needle || $this->normalize((string) $key) === $needle) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function findHeaderRow(array $rows, array $fields): ?int
    {
        $labels = array_map(fn ($f) => $this->normalize((string) ($f['label'] ?? $f['name'])), $fields);

        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $hits = 0;

            foreach ($row as $cell) {
                $cell = $this->normalize((string) $cell);

                if ($cell !== '' && in_array($cell, $labels, true)) {
                    $hits++;
                }
            }

            if ($hits >= 2) {
                return $index;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim($value))) ?: '';
    }
}
