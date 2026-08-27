<?php

namespace App\Console\Commands;

use App\Services\HeltesAccountProvisioner;
use Illuminate\Console\Command;

class ProvisionHeltesAccounts extends Command
{
    protected $signature = 'users:provision-heltes {--dry-run : Зөвхөн тоолох, хадгалахгүй}';

    protected $description = 'Утасны жагсаалтын «Хэлтэс» ангиллын албан хаагчдад нэвтрэх эрх өгнө';

    public function handle(HeltesAccountProvisioner $provisioner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $provisioner->run($dryRun);

        if ($dryRun) {
            $this->comment('Dry-run — өөрчлөлт хадгалаагүй.');
        }

        $this->info(sprintf(
            'Шинэ: %d · Шинэчилсэн: %d · Алгассан: %d',
            $result['created'],
            $result['updated'],
            count($result['skipped']),
        ));

        if ($result['skipped'] !== []) {
            $this->table(
                ['Нэр', 'Шалтгаан'],
                array_map(fn (array $row) => [$row['name'], $row['reason']], $result['skipped']),
            );
        }

        $this->line('Нэвтрэх нэр: гар утас. Нууц үг: нэр + утасны сүүлийн 4 орон.');

        return self::SUCCESS;
    }
}
