<?php

namespace App\Http\Controllers;

use App\Models\DocumentFormat;
use App\Models\Leave;
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

        return view('leaves.slip', [
            'leave' => $leave,
            'copies' => $copies,
            'pageWidth' => $width,
            'pageHeight' => $height,
            'signerTitle' => $leave->signerTitle(),
            'signerName' => (string) $leave->signer,
            'format' => DocumentFormat::defaultFormat(),
            'unit' => $this->unitName($leave->org_name ?: $leave->department?->name),
            'person' => $leave->person_name ?: ($leave->user?->name ?? ''),
            'slipNumber' => $leave->slip_number,
            'year' => $start?->format('Y'),
            'month' => $start?->format('n'),
            'day' => $start?->format('j'),
            'days' => $leave->days,
            'reason' => $leave->reason,
            'kind' => $leave->type,
        ]);
    }

    private function unitName(?string $name): string
    {
        $name = trim((string) $name);

        return trim((string) preg_replace('/\s*хэлт(эс|сийн)$/ui', '', $name));
    }
}
