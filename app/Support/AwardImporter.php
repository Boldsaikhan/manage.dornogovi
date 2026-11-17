<?php

namespace App\Support;

use App\Models\Award;

/**
 * Шагналын бүртгэлд Excel мөрүүдийг толгойгоор тааруулж оруулна.
 */
class AwardImporter
{
    public function __construct(private readonly ModuleRowImporter $rows) {}

    /**
     * @param  array<int, array<int, string>>  $tableRows
     * @param  list<array<string, mixed>>  $columns
     * @return array{
     *     headers: list<string>,
     *     mapping: array<string, int|null>,
     *     fields: array<string, string>,
     *     rows: array<int, array<int, string>>,
     *     entries: list<array<string, mixed>>,
     *     total: int
     * }
     */
    public function preview(array $tableRows, array $columns): array
    {
        $fields = $this->fields($columns);
        $analysed = $this->rows->analyse($tableRows, $fields);
        $entries = $this->rows->build($analysed['rows'], $analysed['mapping'], $fields);

        return [
            'headers' => array_values($analysed['headers']),
            'mapping' => $analysed['mapping'],
            'fields' => collect($fields)->mapWithKeys(fn (array $field) => [$field['name'] => $field['label']])->all(),
            'rows' => array_slice($analysed['rows'], 0, 400),
            'entries' => $entries,
            'total' => count($entries),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  array{category: string, subtype?: string|null, year?: int|null, created_by?: int|null}  $defaults
     */
    public function store(array $entries, array $defaults): int
    {
        $created = 0;

        foreach (array_values($entries) as $entry) {
            if (! is_array($entry) || $created >= 400) {
                continue;
            }

            $payload = array_merge($defaults, array_filter(
                $entry,
                fn ($value) => $value !== null && $value !== '',
            ));

            if ($this->isBlankRow($payload)) {
                continue;
            }

            $year = (int) ($payload['year'] ?? $defaults['year'] ?? date('Y'));
            $parsed = MongolianRegister::parse(
                is_string($payload['register_no'] ?? null) ? $payload['register_no'] : null,
                $year,
            );

            if ($parsed !== null) {
                $payload['register_no'] = $parsed['register'];
                $payload['age'] = $parsed['age'];
                $payload['gender'] = $parsed['gender'];
            }

            if (empty($payload['year'])) {
                $payload['year'] = $year;
            }

            Award::query()->create([
                'category' => $defaults['category'],
                'subtype' => $defaults['subtype'] ?? null,
                'year' => $payload['year'] ?? $year,
                'surname' => $payload['surname'] ?? null,
                'given_name' => $payload['given_name'] ?? null,
                'register_no' => $payload['register_no'] ?? null,
                'age' => $payload['age'] ?? null,
                'gender' => $payload['gender'] ?? null,
                'nominated_award' => $payload['nominated_award'] ?? null,
                'years_in_country' => $payload['years_in_country'] ?? null,
                'years_in_sector' => $payload['years_in_sector'] ?? null,
                'award_date' => $payload['award_date'] ?? null,
                'resolution_number' => $payload['resolution_number'] ?? null,
                'position' => $payload['position'] ?? null,
                'address' => $payload['address'] ?? null,
                'last_award' => $payload['last_award'] ?? null,
                'last_award_date' => $payload['last_award_date'] ?? null,
                'supporting_org' => $payload['supporting_org'] ?? null,
                'forwarded_on' => $payload['forwarded_on'] ?? null,
                'forwarded_letter_no' => $payload['forwarded_letter_no'] ?? null,
                'received_award' => $payload['received_award'] ?? null,
                'presidential_letter' => $payload['presidential_letter'] ?? null,
                'award_name' => $payload['award_name'] ?? null,
                'work_sector' => $payload['work_sector'] ?? null,
                'profession' => $payload['profession'] ?? null,
                'job_title' => $payload['job_title'] ?? null,
                'total_years' => $payload['total_years'] ?? null,
                'position_years' => $payload['position_years'] ?? null,
                'order_ref' => $payload['order_ref'] ?? null,
                'award_note' => $payload['award_note'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $defaults['created_by'] ?? null,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @return list<array{name: string, label: string, type?: string}>
     */
    private function fields(array $columns): array
    {
        $fields = [];

        foreach ($columns as $column) {
            $field = (string) ($column['field'] ?? '');

            if ($field === '' || ! empty($column['readonly'])) {
                continue;
            }

            $type = match ($column['input'] ?? 'text') {
                'number' => 'integer',
                'date' => 'date',
                default => 'string',
            };

            $fields[] = [
                'name' => $field,
                'label' => $this->heading($column),
                'type' => $type,
            ];
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function heading(array $column): string
    {
        if (! empty($column['lines'])) {
            return implode(' ', $column['lines']);
        }

        return (string) ($column['label'] ?? $column['field'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isBlankRow(array $payload): bool
    {
        foreach (['surname', 'given_name', 'register_no', 'nominated_award', 'award_name', 'received_award', 'position', 'job_title'] as $key) {
            if (! empty($payload[$key])) {
                return false;
            }
        }

        return true;
    }
}
