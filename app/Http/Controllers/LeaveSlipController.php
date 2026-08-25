<?php

namespace App\Http\Controllers;

use App\Models\DocumentFormat;
use App\Models\Leave;
use App\Support\ModuleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Чөлөөний хуудас — албан хэрэг хөтлөлтийн загвараар хэвлэх (A4-д 6 ширхэг).
 */
class LeaveSlipController extends Controller
{
    private const MODULE = 'leaves';

    public function show(Request $request, Leave $leave): View
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $copies = (int) $request->query('copies', 6);
        $copies = in_array($copies, [1, 2, 4, 6], true) ? $copies : 6;

        $signer = $request->query('signer', $leave->signer ?: 'acting');
        $signer = $signer === 'head' ? 'head' : 'acting';

        $start = $leave->start_date;

        return view('leaves.slip', [
            'leave' => $leave,
            'copies' => $copies,
            'signer' => $signer,
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
            'actingName' => $request->query('name', 'М.МӨНХБАТ'),
        ]);
    }

    private function unitName(?string $name): string
    {
        $name = trim((string) $name);

        return trim((string) preg_replace('/\s*хэлт(эс|сийн)$/ui', '', $name));
    }
}
