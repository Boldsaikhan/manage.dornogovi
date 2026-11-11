<?php

namespace App\Http\Controllers;

use App\Models\DocumentFormat;
use App\Models\Leave;
use App\Models\PhoneDirectoryEntry;
use App\Support\LeaveSlipNumber;
use App\Support\MongolianCase;
use App\Support\ModuleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Чөлөөний хуудас — албан хэрэг хөтлөлтийн загвараар хэвлэнэ.
 *
 * Нэг A4 босоо хуудсанд хоёр хувь гарна — хэвлэхийн өмнө юу ч асуухгүй.
 * Шаардлагатай бол хаягийн мөрөөр (copies/paper/orient) өөрчилж болно.
 */
class LeaveSlipController extends Controller
{
    private const MODULE = 'leaves';

    public function show(Request $request, Leave $leave): View
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $copies = (int) $request->query('copies', 2);
        $copies = in_array($copies, [1, 2, 4, 6], true) ? $copies : 2;

        // Цаасны хэмжээ, чиглэл — хэвлэхийн өмнө сонгоно.
        $paper = $request->query('paper') === 'a5' ? 'a5' : 'a4';
        $landscape = $request->query('orient') === 'landscape';

        [$width, $height] = $paper === 'a5' ? [148, 210] : [210, 297];

        if ($landscape) {
            [$width, $height] = [$height, $width];
        }

        $start = $leave->start_date;

        // Гараар бичээгүй бол бүртгэлийн хүснэгтийн дэс дугаарыг авна.
        $slipNumber = LeaveSlipNumber::for($leave);

        return view('leaves.slip', [
            'leave' => $leave,
            'copies' => $copies,
            'paper' => $paper,
            'landscape' => $landscape,
            'pageWidth' => $width,
            'pageHeight' => $height,
            'signerTitle' => $leave->signerTitle(),
            'signerName' => (string) $leave->signer,
            'format' => DocumentFormat::defaultFormat(),
            'org' => $this->genitive($leave->org_name ?: $leave->department?->name),
            'unit' => $this->genitive($leave->getAttribute('unit_name')),
            'position' => $this->positionOf($leave),
            'person' => $leave->person_name ?: ($leave->user?->name ?? ''),
            'slipNumber' => $slipNumber,
            'year' => $start?->format('Y'),
            'month' => $start?->format('n'),
            'day' => $start?->format('j'),
            // «11 дүгээр сарын 12-ны» — эгшгийн зохицол.
            'monthOrdinal' => MongolianCase::ordinalSuffix((int) $start?->format('n')),
            'daySuffix' => MongolianCase::daySuffix((int) $start?->format('j')),
            'days' => $leave->days,
            'reason' => self::lowerFirst($leave->reason),
            'kind' => $leave->type,
        ]);
    }

    /**
     * Өгүүлбэрийн дунд орох үгийн эхний үсгийг жижигрүүлнэ.
     *
     * «Хувийн ажлаар» гэж бүртгэсэн ч хуудсан дээр өгүүлбэрийн дунд
     * «хувийн ажлаар» гэж бичигдэнэ. Товчлол (ЗДТГ) бол хөндөхгүй.
     */
    private static function lowerFirst(?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        $first = mb_substr($text, 0, 1);
        $second = mb_substr($text, 1, 1);

        // Бүтнээрээ том үсгээр эхэлсэн товчлолыг хэвээр нь үлдээнэ.
        if ($second !== '' && $second === mb_strtoupper($second) && $second !== mb_strtolower($second)) {
            return $text;
        }

        return mb_strtolower($first).mb_substr($text, 1);
    }

    /** Байгууллага, хэлтсийн нэрийг харьяалахын тийн ялгалд оруулна. */
    private function genitive(?string $name): string
    {
        $name = trim((string) $name);

        return $name === '' ? '' : MongolianCase::genitive($name);
    }

    /**
     * Чөлөө авч буй хүний албан тушаал.
     *
     * Утасны жагсаалтаас нэрээр нь олно. Олдохгүй бол хоосон буцаана —
     * хуудсан дээр гараар бичих зай үлдэнэ.
     */
    private function positionOf(Leave $leave): string
    {
        $name = trim((string) ($leave->person_name ?: ($leave->user?->name ?? '')));

        return trim((string) (PhoneDirectoryEntry::positionFor($name) ?? ''));
    }
}
