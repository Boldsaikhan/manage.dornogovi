<?php

namespace App\Console\Commands;

use App\Models\Decree;
use App\Support\TushaalARegister2026;
use App\Support\TushaalBRegister2026;
use App\Support\ZahiramjBRegister2026;
use Illuminate\Console\Command;

/**
 * Цаасан бүртгэлээс оруулсан мөрүүдийн дутууг нөхнө.
 *
 *   php artisan decrees:fill tushaal-a
 *   php artisan decrees:fill tushaal-b
 *   php artisan decrees:fill zahiramj-b
 *   php artisan decrees:fill all
 */
class FillDecreeRegister extends Command
{
    protected $signature = 'decrees:fill {register=all : tushaal-a | tushaal-b | zahiramj-b | all}';

    protected $description = '2026 оны захирамж, тушаалын бүртгэлийн дутуу мөрүүдийг нөхөх';

    /** [нэр => [төрөл, угтвар, класс]] */
    private const REGISTERS = [
        'tushaal-a' => ['tushaal_a', 'А/', TushaalARegister2026::class],
        'tushaal-b' => ['tushaal_b', 'Б/', TushaalBRegister2026::class],
        'zahiramj-b' => ['zahiramj_b', 'Б/', ZahiramjBRegister2026::class],
    ];

    public function handle(): int
    {
        $requested = strtolower(trim((string) $this->argument('register')));

        // Хуучин бичиглэлийг ч хүлээж авна (a / b).
        $requested = match ($requested) {
            'a' => 'tushaal-a',
            'b' => 'tushaal-b',
            default => $requested,
        };

        $names = $requested === 'all'
            ? array_keys(self::REGISTERS)
            : [$requested];

        foreach ($names as $name) {
            if (! isset(self::REGISTERS[$name])) {
                $this->error(sprintf('«%s» гэсэн бүртгэл алга. tushaal-a, tushaal-b, zahiramj-b, all.', $name));

                return self::FAILURE;
            }

            [$kind, $prefix, $class] = self::REGISTERS[$name];

            $before = Decree::query()->where('kind', $kind)->count();
            $added = $class::fillMissing();
            $after = Decree::query()->where('kind', $kind)->count();

            if ($added === []) {
                $this->info(sprintf('%s — дутуу мөр алга. Нийт %d мөр.', $name, $after));

                continue;
            }

            $this->info(sprintf(
                '%s — нэмэгдсэн: %s (%d → %d мөр)',
                $name,
                implode(', ', array_map(fn (string $n) => $prefix.$n, $added)),
                $before,
                $after,
            ));
        }

        return self::SUCCESS;
    }
}
