<?php

namespace App\Console\Commands;

use App\Models\Decree;
use App\Support\TushaalARegister2026;
use Illuminate\Console\Command;

/**
 * «А» тушаалын 2026 оны бүртгэлийн дутуу мөрийг нөхнө.
 *
 *   php artisan decrees:fill-tushaal-a
 */
class FillTushaalARegister extends Command
{
    protected $signature = 'decrees:fill-tushaal-a';

    protected $description = '2026 оны «А» тушаалын бүртгэлийн дутуу мөрүүдийг нөхөх';

    public function handle(): int
    {
        $before = Decree::query()->where('kind', 'tushaal_a')->count();
        $added = TushaalARegister2026::fillMissing();
        $after = Decree::query()->where('kind', 'tushaal_a')->count();

        if ($added === []) {
            $this->info(sprintf('Дутуу мөр алга. Нийт %d мөр байна.', $after));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Нэмэгдсэн: %s (%d → %d мөр)',
            implode(', ', array_map(fn (string $n) => 'А/'.$n, $added)),
            $before,
            $after,
        ));

        return self::SUCCESS;
    }
}
