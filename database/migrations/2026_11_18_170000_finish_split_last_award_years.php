<?php

use App\Models\Award;
use App\Support\AwardNameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Үлдсэн сүүлд авсан нэрүүдээс оныг дахин хасна (idempotent).
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
                    if (preg_match('/(?<!\d)((?:19|20)\d{2})(?!\d)/u', (string) $award->last_award) !== 1) {
                        continue;
                    }

                    [$name, $date] = $names->splitLastAward(
                        $award->last_award,
                        $award->last_award_date,
                    );

                    if ($name === $award->last_award) {
                        continue;
                    }

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
        //
    }
};
