<?php

namespace App\Console\Commands;

use App\Services\HeltesAccountProvisioner;
use Illuminate\Console\Command;

class ProvisionHeltesAccounts extends Command
{
    protected $signature = 'users:provision-heltes
                            {--dry-run : Зөвхөн тоолох, хадгалахгүй}
                            {--with-sms : SMS илгээх (SMS_ENABLED=true байх ёстой)}
                            {--emails-only : Зөвхөн и-мэйлийг нэр@dornogovi.gov.mn болгох}
                            {--passwords-only : Нууц үгийг ZDTG@2026 болгох}';

    protected $description = 'Утасны жагсаалтын «Хэлтэс» ангиллын албан хаагчдад нэвтрэх эрх өгнө';

    public function handle(HeltesAccountProvisioner $provisioner): int
    {
        if ($this->option('emails-only')) {
            $updated = $provisioner->syncStaffEmails();
            $this->info(sprintf('И-мэйл шинэчилсэн: %d', $updated));
            $this->line('И-мэйл: нэр@dornogovi.gov.mn. Нэвтрэх: гар утас. Нууц үг: ZDTG@2026.');

            return self::SUCCESS;
        }

        if ($this->option('passwords-only')) {
            $updated = $provisioner->syncStaffPasswords();
            $this->info(sprintf('Нууц үг шинэчилсэн: %d', $updated));
            $this->line('Нууц үг: ZDTG@2026.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $withSms = (bool) $this->option('with-sms');
        $result = $provisioner->run($dryRun, $withSms);

        if ($dryRun) {
            $this->comment('Dry-run — өөрчлөлт хадгалаагүй.');
        }

        $this->info(sprintf(
            'Шинэ: %d · Шинэчилсэн: %d · Алгассан: %d',
            $result['created'],
            $result['updated'],
            count($result['skipped']),
        ));

        if ($result['sms_sent'] > 0 || $result['sms_failed'] > 0) {
            $this->line(sprintf(
                'SMS: %d амжилттай, %d амжилтгүй',
                $result['sms_sent'],
                $result['sms_failed'],
            ));
        }

        if ($result['skipped'] !== []) {
            $this->table(
                ['Нэр', 'Шалтгаан'],
                array_map(fn (array $row) => [$row['name'], $row['reason']], $result['skipped']),
            );
        }

        $this->line('И-мэйл: нэр@dornogovi.gov.mn. Нэвтрэх: гар утас. Нууц үг: ZDTG@2026.');

        return self::SUCCESS;
    }
}
