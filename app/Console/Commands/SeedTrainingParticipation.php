<?php

namespace App\Console\Commands;

use App\Services\TrainingParticipationSeeder;
use Illuminate\Console\Command;

class SeedTrainingParticipation extends Command
{
    protected $signature = 'tasks:seed-training-participation';

    protected $description = '«Сургалтын идэвх оролцоо»-д хэлтсийн албан хаагч бүрт «сургалтад идэвхтэй оролцох» үүрэг нэмнэ';

    public function handle(TrainingParticipationSeeder $seeder): int
    {
        $result = $seeder->run();

        $this->info(sprintf(
            'Хэлтэс: %d · Шинэ мөр: %d · Аль хэдийн байсан: %d · source_id=%d',
            $result['people'],
            $result['created'],
            $result['skipped'],
            $result['source_id'],
        ));

        return self::SUCCESS;
    }
}
