<?php

namespace App\Http\Controllers;

use App\Models\TravelAssignment;
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

    /** Батлах албан тушаалтны толгойн бичвэр. */
    private const SIGNERS = [
        'governor' => [
            'ДОРНОГОВЬ АЙМГИЙН ЗАСАГ ДАРГА',
        ],
        'deputy' => [
            'ДОРНОГОВЬ АЙМГИЙН ЗАСАГ',
            'ДАРГЫН ОРЛОГЧ',
        ],
        'chief' => [
            'ДОРНОГОВЬ АЙМГИЙН ЗДТГ-ЫН',
            'ДАРГЫН АЛБАН ҮҮРГИЙГ ТҮР ОРЛОН',
            'ГҮЙЦЭТГЭГЧ',
        ],
    ];

    public function show(Request $request, TravelAssignment $assignment): View
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $approver = array_key_exists($assignment->approver, self::SIGNERS)
            ? $assignment->approver
            : 'governor';

        return view('assignments.sheet', [
            'assignment' => $assignment,
            'lines' => self::SIGNERS[$approver],
            'signerName' => $this->signerName($approver),
            'approverLabel' => $assignment->approverLabel(),
            'year' => optional($assignment->start_date)?->format('Y') ?? now()->format('Y'),
            'period' => $this->period($assignment),
        ]);
    }

    /**
     * Гарын үсэг зурах хүний нэр — утасны жагсаалтаас албан тушаалаар нь олно.
     */
    private function signerName(string $approver): string
    {
        $needle = match ($approver) {
            'deputy' => 'засаг даргын орлогч',
            'chief' => 'здтг-ын дарга',
            default => 'засаг дарга',
        };

        $entry = \App\Models\PhoneDirectoryEntry::query()
            ->whereRaw('LOWER(position) LIKE ?', ['%'.$needle.'%'])
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->first();

        return trim((string) ($entry->person_name ?? ''));
    }

    /** «2026-09-01 — 2026-09-05» хэлбэрийн хугацаа. */
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
