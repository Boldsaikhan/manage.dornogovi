<?php

namespace App\Support;

use App\Models\Department;
use App\Models\ReportRowEdit;

/**
 * Тайлангийн хүснэгтэд Excel, Word, PDF-ээс мөр нэмнэ.
 * Эх JSON-д бичихгүй — deploy тутамд тэр файл солигддог.
 */
class ReportTableImporter
{
    public function __construct(private readonly ModuleRowImporter $rows) {}

    /**
     * @param  array<int, array<int, string>>  $tableRows
     * @param  list<array{key?: string, label?: string}>  $columns
     * @return array{
     *     headers: list<string>,
     *     mapping: array<string, int|null>,
     *     fields: array<string, string>,
     *     rows: array<int, array<int, string>>,
     *     entries: list<array<string, string|null>>,
     *     total: int
     * }
     */
    public function preview(array $tableRows, array $columns): array
    {
        $fields = $this->fields($columns);
        $analysed = $this->rows->analyse($tableRows, $fields);

        if ($analysed['headers'] === [] && $analysed['rows'] !== []) {
            $analysed['headers'] = array_map(fn ($cell) => trim((string) $cell), $analysed['rows'][0]);
            $analysed['rows'] = array_values(array_slice($analysed['rows'], 1));
        }

        $analysed['mapping'] = $this->guess($analysed['headers'], $fields);

        $dataRows = array_slice($analysed['rows'], 0, 400);
        $entries = $this->entries($dataRows, $analysed['mapping'], $fields);

        return [
            'headers' => array_values($analysed['headers']),
            'mapping' => $analysed['mapping'],
            'fields' => collect($fields)->mapWithKeys(fn (array $field) => [$field['name'] => $field['label']])->all(),
            'rows' => $dataRows,
            'entries' => $entries,
            'total' => count($entries),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  list<array{key?: string, label?: string}>  $columns
     */
    public function store(string $reportKey, array $entries, array $columns, ?int $userId): int
    {
        $allowed = [];

        foreach ($columns as $column) {
            $key = (string) ($column['key'] ?? '');

            if ($key !== '') {
                $allowed[] = $key;
            }
        }

        $byName = [];

        foreach (Department::query()->get(['id', 'name']) as $department) {
            $byName[mb_strtolower(trim($department->name))] = $department->id;
        }

        $start = ReportRows::length($reportKey);
        $now = now();
        $inserts = [];
        $added = 0;

        foreach (array_values($entries) as $entry) {
            if (! is_array($entry) || $added >= 400) {
                continue;
            }

            $cells = [];

            foreach ($allowed as $key) {
                $value = trim((string) ($entry[$key] ?? ''));

                if ($value === '') {
                    continue;
                }

                $cells[$key] = mb_substr($value, 0, 5000);
            }

            if ($cells === []) {
                continue;
            }

            $index = $start + $added;
            $added++;

            foreach ($cells as $key => $value) {
                $departmentId = null;
                $stored = $value;

                if ($key === ReportRowEdit::DEPARTMENT_COLUMN) {
                    $match = $byName[mb_strtolower($value)] ?? null;

                    if ($match) {
                        $departmentId = $match;
                        $stored = null;
                    }
                }

                $inserts[] = [
                    'report_key' => $reportKey,
                    'row_index' => $index,
                    'column_key' => $key,
                    'value' => $stored,
                    'department_id' => $departmentId,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($inserts, 400) as $chunk) {
            ReportRowEdit::query()->insert($chunk);
        }

        return $added;
    }

    /**
     * @param  list<array{key?: string, label?: string}>  $columns
     * @return list<array{name: string, label: string, type: string}>
     */
    private function fields(array $columns): array
    {
        $fields = [];

        foreach ($columns as $column) {
            $name = (string) ($column['key'] ?? '');

            if ($name === '') {
                continue;
            }

            $fields[] = [
                'name' => $name,
                'label' => (string) ($column['label'] ?? $name),
                'type' => 'text',
            ];
        }

        return $fields;
    }

    /**
     * «№» мэт тэмдэгт шошго хоосон болж бүх баганад таарахгүй.
     *
     * @param  list<string>  $headers
     * @param  list<array{name: string, label: string}>  $fields
     * @return array<string, int|null>
     */
    private function guess(array $headers, array $fields): array
    {
        $normalized = array_map(fn ($header) => $this->normalize((string) $header), $headers);
        $used = [];
        $mapping = [];

        foreach ($fields as $field) {
            $label = trim($field['label']);
            $candidate = $this->normalize($label);
            $found = null;

            foreach ($normalized as $index => $header) {
                if (in_array($index, $used, true)) {
                    continue;
                }

                $raw = trim((string) ($headers[$index] ?? ''));

                if ($candidate === '') {
                    if ($raw !== '' && $raw === $label) {
                        $found = $index;
                        break;
                    }

                    continue;
                }

                if ($header === '') {
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

            $mapping[$field['name']] = $found;
        }

        return $mapping;
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim($value))) ?: '';
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string, int|null>  $mapping
     * @param  list<array{name: string, label: string}>  $fields
     * @return list<array<string, string|null>>
     */
    private function entries(array $rows, array $mapping, array $fields): array
    {
        $out = [];

        foreach ($rows as $row) {
            $entry = [];
            $filled = false;

            foreach ($fields as $field) {
                $name = $field['name'];
                $index = $mapping[$name] ?? null;
                $raw = $index === null ? '' : trim((string) ($row[$index] ?? ''));
                $entry[$name] = $raw === '' ? null : mb_substr($raw, 0, 5000);
                $filled = $filled || $raw !== '';
            }

            if ($filled) {
                $out[] = $entry;
            }
        }

        return $out;
    }
}
