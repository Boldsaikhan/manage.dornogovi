<?php

namespace App\Console\Commands;

use App\Models\PhoneDirectoryEntry;
use Illuminate\Console\Command;

/**
 * Утасны лавлахад зарим сумын орлогч, сургуулийн захирал, багийн дарга
 * нарын мөрүүдэд «Овог нэр» болон «Албан тушаал» багана эрэмбэ нь
 * солигдож бичигдсэн байсныг нэг удаа засна (жишээ нь person_name=
 * «Засаг даргын орлогч», position=«М.Мөнх-Эрдэнэ» — буруу эргэсэн).
 *
 *   php artisan phone-directory:fix-swapped-names {--dry-run}
 */
class FixSwappedPhoneDirectoryNames extends Command
{
    protected $signature = 'phone-directory:fix-swapped-names {--dry-run : Зөвхөн илрүүлсэн мөрүүдийг харуулаад засахгүй}';

    protected $description = 'Овог нэр/Албан тушаал нь эрэмбэ солигдож бичигдсэн утасны лавлахын мөрүүдийг засна';

    /** Албан тушаал/байгууллагын нэрэнд байдаг түлхүүр үгс. */
    private const TITLE_WORDS = [
        'дарга', 'орлогч', 'мэргэжилтэн', 'нарийн бичиг', 'эрхлэгч', 'нягтлан',
        'ажилтан', 'зөвлөх', 'хэлтэс', 'газар', 'алба', 'сум', 'захирал',
        'эмч', 'багш', 'инженер', 'менежер', 'хурал', 'товчоо', 'төв',
        'баг,', 'багийн', 'хороо',
    ];

    public function handle(): int
    {
        $rows = PhoneDirectoryEntry::query()->orderBy('id')->get(['id', 'person_name', 'position', 'org_name']);

        $swapped = $rows->filter(fn (PhoneDirectoryEntry $row) => self::looksLikeTitleOrOrg((string) $row->person_name)
            && self::looksLikeName((string) $row->position));

        if ($swapped->isEmpty()) {
            $this->info('Солигдсон мөр олдсонгүй.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d мөр олдлоо:', $swapped->count()));

        foreach ($swapped as $row) {
            $this->line(sprintf(
                '  #%d [%s] «%s» ↔ «%s»',
                $row->id,
                $row->org_name,
                $row->person_name,
                $row->position,
            ));
        }

        if ($this->option('dry-run')) {
            $this->comment('--dry-run тул юу ч засаагүй.');

            return self::SUCCESS;
        }

        foreach ($swapped as $row) {
            $row->update([
                'person_name' => $row->position,
                'position' => $row->person_name,
            ]);
        }

        $this->info(sprintf('%d мөрийг зассан.', $swapped->count()));

        return self::SUCCESS;
    }

    private static function looksLikeTitleOrOrg(string $value): bool
    {
        $v = mb_strtolower($value);
        if ($v === '') {
            return false;
        }

        foreach (self::TITLE_WORDS as $word) {
            if (str_contains($v, $word)) {
                return true;
            }
        }

        return false;
    }

    /** «Х.Нэр» эсвэл «Нэр Овог» хэлбэртэй хүний нэр мэт харагдах эсэх. */
    private static function looksLikeName(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || self::looksLikeTitleOrOrg($value)) {
            return false;
        }

        if (preg_match('/\d/u', $value)) {
            return false;
        }

        if (preg_match('/^[А-ЯӨҮЁ]\.[А-Яа-яөүёЁӨҮ\-]+$/u', $value)) {
            return true;
        }

        $words = preg_split('/\s+/u', $value) ?: [];
        if (count($words) !== 2) {
            return false;
        }

        foreach ($words as $word) {
            if (! preg_match('/^[А-ЯӨҮЁ][а-яөүёА-Яa-я\-]+$/u', $word)) {
                return false;
            }
        }

        return true;
    }
}
