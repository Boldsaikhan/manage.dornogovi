<?php

namespace App\Console\Commands;

use App\Models\Decree;
use App\Support\TushaalARegister2026;
use App\Support\TushaalBRegister2026;
use Illuminate\Console\Command;

/**
 * «А» тушаалын 2026 оны бүртгэлийн дутуу мөрийг нөхнө.
 *
 *   php artisan decrees:fill-tushaal-a
 */
class FillTushaalARegister extends Command
{
    protected $signature = 'decrees:fill-tushaal {kind=a : a эсвэл b}';

    protected $description = '2026 оны «А»/«Б» тушаалын бүртгэлийн дутуу мөрүүдийг нөхөх';

    public function handle(): int
    {
        $kind = strtolower((string) $this->argument('kind')) === 'b' ? 'tushaal_b' : 'tushaal_a';

        $before = Decree::query()->where('kind', $kind)->count();
        $added = $kind === 'tushaal_b'
            ? TushaalBRegister2026::fillMissing()
            : TushaalARegister2026::fillMissing();
        $after = Decree::query()->where('kind', $kind)->count();

        if ($added === []) {
            $this->info(sprintf('Дутуу мөр алга. Нийт %d мөр байна.', $after));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Нэмэгдсэн: %s (%d → %d мөр)',
            implode(', ', array_map(
                fn (string $n) => ($kind === 'tushaal_b' ? 'Б/' : 'А/').$n,
                $added,
            )),
            $before,
            $after,
        ));

        return self::SUCCESS;
    }
}
