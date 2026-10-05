<?php

namespace App\Console\Commands;

use App\Models\AnnualLeave;
use Illuminate\Console\Command;

/**
 * Ажлын өдрөөр дуусах огноо тооцдог болсны дараа урьд нь (ням, бямба орсон
 * хуанлийн өдрөөр) бодогдсон хуучин мөрүүдийг нэг удаа дахин бодож засна.
 *
 *   php artisan annual-leaves:recompute-end-dates
 */
class RecomputeAnnualLeaveEndDates extends Command
{
    protected $signature = 'annual-leaves:recompute-end-dates';

    protected $description = 'Ээлжийн амралтын бүх мөрийн дуусах огноог ажлын өдрөөр дахин бодно';

    public function handle(): int
    {
        $rows = AnnualLeave::query()
            ->whereNotNull('start_date')
            ->whereNotNull('entitled_days')
            ->get();

        $changed = 0;

        foreach ($rows as $row) {
            $newEndDate = AnnualLeave::endDateFor($row->start_date, (int) $row->entitled_days);

            if ($newEndDate !== optional($row->end_date)->toDateString()) {
                $row->update(['end_date' => $newEndDate]);
                $changed++;
            }
        }

        $this->info(sprintf('Шалгасан: %d мөр, шинэчилсэн: %d мөр.', $rows->count(), $changed));

        return self::SUCCESS;
    }
}
