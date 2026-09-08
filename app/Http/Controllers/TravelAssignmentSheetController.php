<?php

namespace App\Http\Controllers;

use App\Models\TravelAssignment;
use App\Support\AssignmentSheet;
use App\Support\ModuleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * «ТОМИЛОЛТЫН УДИРДАМЖ» маягтыг хэвлэх.
 *
 * Батлах албан тушаалтнаас хамаарч толгойн «БАТЛАВ» хэсэг өөрчлөгдөнө.
 */
class TravelAssignmentSheetController extends Controller
{
    private const MODULE = 'assignments';

    public function show(Request $request, TravelAssignment $assignment): View
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        return view('assignments.sheet', [
            'assignment' => $assignment,
            'lines' => AssignmentSheet::lines($assignment->approver),
            'signerName' => AssignmentSheet::signerName($assignment->approver),
            'approverLabel' => $assignment->approverLabel(),
            'year' => optional($assignment->start_date)?->format('Y') ?? now()->format('Y'),
            'period' => $this->period($assignment),
        ]);
    }

    /** «2026.09.01 — 2026.09.05» хэлбэрийн хугацаа. */
    private function period(TravelAssignment $assignment): string
    {
        $start = optional($assignment->start_date)?->format('Y.m.d');
        $end = optional($assignment->end_date)?->format('Y.m.d');

        if ($start && $end) {
            return $start === $end ? $start : $start.' — '.$end;
        }

        return (string) ($start ?? $end ?? '');
    }
}
