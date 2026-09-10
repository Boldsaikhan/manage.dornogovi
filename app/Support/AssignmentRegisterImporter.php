<?php

namespace App\Support;

use App\Models\TravelAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Томилолтын бүртгэлийг Excel/Word файлаас оруулна.
 *
 * Цаасан маягтын багана: Д/д · Овог нэр · Албан тушаал · Хаана ·
 * Ямар ажлаар · Хэзээнээс · Хэд хоног.
 */
class AssignmentRegisterImporter
{
    /** Оруулж болох талбарууд. */
    public const FIELDS = [
        'person_name' => 'Овог нэр',
        'position' => 'Албан тушаал',
        'destination' => 'Хаана',
        'purpose' => 'Ямар ажлаар',
        'start_date' => 'Хэзээнээс',
        'days' => 'Хэд хоног',
        'order_number' => 'Тушаалын дугаар',
    ];

    private const SYNONYMS = [
        'person_name' => ['овогнэр', 'нэр', 'албанхаагч', 'овог'],
        'position' => ['албантушаал', 'тушаал', 'албантушаалтан'],
        'destination' => ['хаана', 'очихгазар', 'газар'],
        'purpose' => ['ямаражлаар', 'зорилго', 'ажил', 'ямаражил'],
        'start_date' => ['хэзээнээс', 'эхлэх', 'огноо', 'эхлэхогноо'],
        'days' => ['хэдхоног', 'хоног', 'хугацаа'],
        'order_number' => ['тушаалындугаар', 'дугаар'],
    ];

    public function __construct(private readonly int $year = 2026) {}

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

        return [
            'headers' => $headers,
            'mapping' => $this->guessMapping($headers),
            'rows' => $headerIndex === null ? $rows : array_values(array_slice($rows, $headerIndex + 1)),
        ];
    }

    /**
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
     * Тааруулсан баганаар мөрүүдийг бэлтгэнэ (хадгалахгүй).
     *
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string, int|null>  $mapping
     * @return list<array<string, mixed>>
     */
    public function build(array $rows, array $mapping): array
    {
        $out = [];
        $lastStart = null;

        foreach ($rows as $row) {
            $value = function (string $field) use ($row, $mapping): string {
                $index = $mapping[$field] ?? null;

                return $index === null ? '' : trim((string) ($row[$index] ?? ''));
            };

            $person = $value('person_name');
            $start = $this->date($value('start_date')) ?? $lastStart;
            $days = $this->days($value('days'));

            // Нэргүй мөр (хоосон эсвэл нийлбэр) — алгасна.
            if ($person === '') {
                continue;
            }

            $lastStart = $start;

            $out[] = [
                'person_name' => $person,
                'position' => $value('position') ?: null,
                'destination' => $value('destination') ?: null,
                'purpose' => $value('purpose') ?: null,
                'start_date' => $start,
                'days' => $days,
                'end_date' => $start && $days ? Carbon::parse($start)->addDays($days - 1)->format('Y-m-d') : $start,
                'order_number' => $value('order_number') ?: null,
            ];
        }

        return $out;
    }

    /**
     * Бэлтгэсэн мөрүүдийг хадгална.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array{created: int, skipped: int}
     */
    public function store(string $approver, array $entries): array
    {
        $users = User::query()
            ->whereNotNull('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $u) => [DirectoryNameResolver::key((string) $u->name) => $u->id])
            ->all();

        $created = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            $person = trim((string) ($entry['person_name'] ?? ''));

            if ($person === '') {
                $skipped++;

                continue;
            }

            // Ижил хүн, ижил огноо, ижил газар давхардвал дахин оруулахгүй.
            $start = $entry['start_date'] ?? null;

            $exists = TravelAssignment::query()
                ->where('approver', $approver)
                ->where('person_name', $person)
                // Огноо нь цаг хамт хадгалагддаг тул зөвхөн өдрөөр нь харьцуулна.
                ->when($start, fn ($query) => $query->whereDate('start_date', $start))
                ->when(! $start, fn ($query) => $query->whereNull('start_date'))
                ->where('destination', $entry['destination'] ?? null)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            TravelAssignment::query()->create([
                'approver' => $approver,
                'user_id' => $users[DirectoryNameResolver::key($person)] ?? null,
                'person_name' => $person,
                'position' => $entry['position'] ?? null,
                'destination' => $entry['destination'] ?? null,
                'purpose' => $entry['purpose'] ?? null,
                'start_date' => $entry['start_date'] ?? null,
                'end_date' => $entry['end_date'] ?? null,
                'order_number' => $entry['order_number'] ?? null,
                'status' => 'approved',
            ]);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * «1.13», «05.21», «2.10.», «9.07» → 2026-01-13 гэх мэт.
     *
     * Цаасан бүртгэлд сар.өдөр хэлбэрээр бичдэг тул оныг нь нөхнө.
     */
    public function date(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        // Excel-ийн серийн дугаар.
        if (preg_match('/^\d{5}(\.\d+)?$/', $raw)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $raw)->format('Y-m-d');
        }

        // Бүтэн огноо.
        if (preg_match('/(\d{4})\D(\d{1,2})\D(\d{1,2})/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        // Сар.өдөр.
        if (preg_match('/^(\d{1,2})\D+(\d{1,2})\D*$/', $raw, $m)) {
            $month = (int) $m[1];
            $day = (int) $m[2];

            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return sprintf('%04d-%02d-%02d', $this->year, $month, $day);
            }
        }

        return null;
    }

    public function days(string $raw): ?int
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits === '' ? null : max(1, (int) $digits);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function findHeaderRow(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $normalized = implode(' ', array_map(fn ($c) => $this->normalize((string) $c), $row));

            if (str_contains($normalized, 'хаана') || str_contains($normalized, 'ямаражлаар')) {
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
