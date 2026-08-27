<?php

namespace App\Console\Commands;

use App\Services\HeltesAccountProvisioner;
use Illuminate\Console\Command;

class ProvisionHeltesAccounts extends Command
{
    protected $signature = 'users:provision-heltes
                            {--dry-run : Зөвхөн тоолох, хадгалахгүй}
                            {--emails-only : Зөвхөн и-мэйлийг нэр@dornogovi.gov.mn болгох}
                            {--passwords-only : Нууц үгийг ZDTG+утас болгох}';

    protected $description = 'Утасны жагсаалтын «Хэлтэс» ангиллын албан хаагчдад нэвтрэх эрх өгнө';

    public function handle(HeltesAccountProvisioner $provisioner): int
    {
        if ($this->option('emails-only')) {
            $updated = $provisioner->syncStaffEmails();
            $this->info(sprintf('И-мэйл шинэчилсэн: %d', $updated));
            $this->line('И-мэйл: нэр@dornogovi.gov.mn. Нэвтрэх: гар утас. Нууц үг: ZDTG + утас.');

            return self::SUCCESS;
        }

        if ($this->option('passwords-only')) {
            $updated = $provisioner->syncStaffPasswords();
            $this->info(sprintf('Нууц үг шинэчилсэн: %d', $updated));
            $this->line('Нууц үг: ZDTG + утасны дугаар (жишээ: ZDTG99178904).');

            return self::SUCCESS;
        }

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

        $this->line('И-мэйл: нэр@dornogovi.gov.mn. Нэвтрэх: гар утас. Нууц үг: ZDTG + утас.');

        return self::SUCCESS;
    }
}
