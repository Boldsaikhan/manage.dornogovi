<?php

namespace App\Console\Commands;

use App\Services\PhoneDirectoryStaffSyncer;
use App\Support\PhoneDirectoryStaffListParser;
use Illuminate\Console\Command;

class SyncPhoneDirectoryStaff extends Command
{
    protected $signature = 'phone-directory:sync-staff
                            {file? : Excel (.xlsx) эсвэл JSON зам}
                            {--dry-run : Зөвхөн тоолох}';

    protected $description = 'Excel/JSON-ийн Овог·Нэр·утас·и-мэйл жагсаалтыг утасны бүртгэлд тааруулна';

    public function handle(PhoneDirectoryStaffListParser $parser, PhoneDirectoryStaffSyncer $syncer): int
    {
        $file = $this->argument('file') ?: database_path('data/phone-list-staff.json');

        if (! is_file($file)) {
            $this->error('Файл олдсонгүй: '.$file);

            return self::FAILURE;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $people = $ext === 'json'
            ? json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)
            : $parser->parse($file);

        if (! is_array($people) || $people === []) {
            $this->error('Жагсаалт хоосон байна.');

            return self::FAILURE;
        }

        $result = $syncer->sync($people, (bool) $this->option('dry-run'));

        if ($this->option('dry-run')) {
            $this->comment('Dry-run — хадгалаагүй.');
        }

        $this->info(sprintf(
            'Утасны жагсаалт: %d шинэчилсэн · %d нэмсэн · %d хэрэглэгч · %d алгассан.',
            $result['updated'],
            $result['created'],
            $result['users'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
