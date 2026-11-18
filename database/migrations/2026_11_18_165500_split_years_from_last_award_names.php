<?php

use App\Models\Award;
use App\Support\AwardNameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Нэг удаа: сүүлд авсан шагналын нэрээс оныг хасаж огноо багана руу шилжүүлнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('awards')) {
            return;
        }

        $names = app(AwardNameNormalizer::class);

        Award::query()
            ->whereNotNull('last_award')
            ->where('last_award', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($names) {
                foreach ($rows as $award) {
                    [$name, $date] = $names->splitLastAward(
                        $award->last_award,
                        $award->last_award_date,
                    );

                    if ($name === $award->last_award
                        && ($date === null || optional($award->last_award_date)?->format('Y-m-d') === $date)
                    ) {
                        continue;
                    }

                    // Эвдэрсэн encoding-тэй мөрийг алгасна.
                    if (is_string($name) && ! mb_check_encoding($name, 'UTF-8')) {
                        continue;
                    }

                    try {
                        $award->forceFill([
                            'last_award' => $name,
                            'last_award_date' => $date,
                        ])->saveQuietly();
                    } catch (\Throwable) {
                        continue;
                    }
                }
            });
    }

    public function down(): void
    {
        // Өгөгдөл засвар — буцаах боломжгүй.
    }
};
