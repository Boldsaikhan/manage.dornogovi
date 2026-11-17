<?php

namespace App\Console\Commands;

use App\Models\Award;
use App\Models\AwardType;
use App\Support\MongolianRegister;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Төрийн дээд одон, медалийн эх Excel/Word-оос бэлдсэн JSON-ийг оруулна.
 *
 *   php artisan awards:import-state-high
 *   php artisan awards:import-state-high --fresh
 */
class ImportStateHighAwards extends Command
{
    protected $signature = 'awards:import-state-high
                            {--file= : JSON замыг гараар заах}
                            {--fresh : state_high мөрүүдийг устгаад дахин оруулах}';

    protected $description = 'Төрийн дээд шагналын бүртгэлийг JSON-оос оруулах';

    public function handle(): int
    {
        $path = (string) ($this->option('file') ?: storage_path('app/imports/state_high_awards.json'));

        if (! is_file($path)) {
            $this->error("JSON олдсонгүй: {$path}");
            $this->line('Эхлээд: python scripts/build_state_high_awards_import.py');

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload) || ! is_array($payload['entries'] ?? null)) {
            $this->error('JSON бүтэц буруу.');

            return self::FAILURE;
        }

        /** @var list<array<string, mixed>> $entries */
        $entries = array_values($payload['entries']);
        $types = is_array($payload['award_types'] ?? null) ? $payload['award_types'] : [];

        if ($this->option('fresh')) {
            $deleted = Award::query()->where('category', 'state_high')->delete();
            $this->warn("state_high хуучин мөр устгав: {$deleted}");
        }

        $this->seedAwardTypes($types);

        $created = 0;
        $skipped = 0;
        $existingKeys = $this->existingKeys();

        DB::transaction(function () use ($entries, &$created, &$skipped, &$existingKeys) {
            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    $skipped++;

                    continue;
                }

                $row = $this->normalizeEntry($entry);

                if ($this->isBlank($row)) {
                    $skipped++;

                    continue;
                }

                $key = $this->key($row);

                if (isset($existingKeys[$key])) {
                    $skipped++;

                    continue;
                }

                Award::query()->create($row);
                $existingKeys[$key] = true;
                $created++;
            }
        });

        $total = Award::query()->where('category', 'state_high')->count();

        $this->info("Оруулсан: {$created}, алгассан: {$skipped}, нийт state_high: {$total}");

        if (! empty($payload['stats']) && is_array($payload['stats'])) {
            foreach ($payload['stats'] as $file => $count) {
                $this->line("  · {$file}: {$count}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<mixed>  $types
     */
    private function seedAwardTypes(array $types): void
    {
        $order = (int) AwardType::query()->max('sort_order');

        foreach ($types as $name) {
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            $exists = AwardType::query()->where('name', $name)->exists();

            if ($exists) {
                continue;
            }

            $order++;
            AwardType::query()->create([
                'name' => $name,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return array<string, true>
     */
    private function existingKeys(): array
    {
        $keys = [];

        Award::query()
            ->where('category', 'state_high')
            ->get(['year', 'register_no', 'surname', 'given_name', 'nominated_award'])
            ->each(function (Award $award) use (&$keys) {
                $keys[$this->key([
                    'year' => $award->year,
                    'register_no' => $award->register_no,
                    'surname' => $award->surname,
                    'given_name' => $award->given_name,
                    'nominated_award' => $award->nominated_award,
                ])] = true;
            });

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function normalizeEntry(array $entry): array
    {
        $year = (int) ($entry['year'] ?? date('Y'));
        if ($year < 1990 || $year > 2100) {
            $year = (int) date('Y');
        }

        $register = is_string($entry['register_no'] ?? null) ? $entry['register_no'] : null;
        $age = $this->nullableInt($entry['age'] ?? null);
        $gender = $this->nullableString($entry['gender'] ?? null);

        $parsed = MongolianRegister::parse($register, $year);

        if ($parsed !== null) {
            $register = $parsed['register'];
            $age = $parsed['age'];
            $gender = $parsed['gender'];
        }

        return [
            'category' => 'state_high',
            'subtype' => null,
            'year' => $year,
            'surname' => $this->nullableString($entry['surname'] ?? null),
            'given_name' => $this->nullableString($entry['given_name'] ?? null),
            'register_no' => $register,
            'age' => $age,
            'gender' => $gender,
            'nominated_award' => $this->nullableString($entry['nominated_award'] ?? null),
            'years_in_country' => $this->nullableInt($entry['years_in_country'] ?? null),
            'years_in_sector' => $this->nullableInt($entry['years_in_sector'] ?? null),
            'award_date' => $this->nullableDate($entry['award_date'] ?? null),
            'resolution_number' => $this->nullableString($entry['resolution_number'] ?? null),
            'position' => $this->nullableString($entry['position'] ?? null),
            'address' => $this->nullableString($entry['address'] ?? null),
            'last_award' => $this->nullableString($entry['last_award'] ?? null),
            'last_award_date' => $this->nullableDate($entry['last_award_date'] ?? null),
            'supporting_org' => $this->nullableString($entry['supporting_org'] ?? null),
            'forwarded_on' => $this->nullableDate($entry['forwarded_on'] ?? null),
            'forwarded_letter_no' => $this->nullableString($entry['forwarded_letter_no'] ?? null),
            'received_award' => $this->nullableString($entry['received_award'] ?? null),
            'presidential_letter' => $this->nullableString($entry['presidential_letter'] ?? null),
            'award_name' => $this->nullableString($entry['award_name'] ?? null),
            'work_sector' => $this->nullableString($entry['work_sector'] ?? null),
            'profession' => $this->nullableString($entry['profession'] ?? null),
            'job_title' => $this->nullableString($entry['job_title'] ?? null),
            'total_years' => $this->nullableInt($entry['total_years'] ?? null),
            'position_years' => $this->nullableInt($entry['position_years'] ?? null),
            'order_ref' => $this->nullableString($entry['order_ref'] ?? null),
            'award_note' => $this->nullableString($entry['award_note'] ?? null),
            'notes' => $this->nullableString($entry['notes'] ?? null),
            'created_by' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isBlank(array $row): bool
    {
        foreach (['surname', 'given_name', 'register_no', 'nominated_award', 'received_award', 'position'] as $key) {
            if (! empty($row[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function key(array $row): string
    {
        return implode('|', [
            (string) ($row['year'] ?? ''),
            mb_strtoupper(preg_replace('/\s+/u', '', (string) ($row['register_no'] ?? '')) ?? ''),
            mb_strtolower((string) ($row['surname'] ?? '')),
            mb_strtolower((string) ($row['given_name'] ?? '')),
            mb_strtolower((string) ($row['nominated_award'] ?? '')),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 5000);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/-?\d+/', $value, $m)) {
            return (int) $m[0];
        }

        return null;
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = $this->nullableString($value);

        if ($text === null) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return $text;
        }

        // Зөвхөн он (2005) → 2005-01-01
        if (preg_match('/^\d{4}$/', $text) === 1) {
            $year = (int) $text;

            return ($year >= 1900 && $year <= 2100)
                ? sprintf('%04d-01-01', $year)
                : null;
        }

        if (preg_match('/^(\d{4})[.\-\/]\d{1,2}[.\-\/]\d{1,2}$/', $text, $m) === 1) {
            $year = (int) $m[1];

            return ($year >= 1900 && $year <= 2100)
                ? sprintf('%04d-01-01', $year)
                : null;
        }

        return null;
    }
}
