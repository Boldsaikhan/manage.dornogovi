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
            // Батлагчийг сонгосон бол түүний албан тушаалаар толгойг бүрдүүлнэ.
            'lines' => AssignmentSheet::linesFor($assignment->approver, $assignment->approved_by),
            // Сонгосон хүн байвал тэр, үгүй бол табын батлагчийн нэр.
            'signerName' => $assignment->approved_by
                ?: AssignmentSheet::signerName($assignment->approver),
            'approverLabel' => $assignment->approverLabel(),
            'year' => optional($assignment->start_date)?->format('Y') ?? now()->format('Y'),
            'period' => $this->period($assignment),
            'number' => $this->rowNumber($assignment),
            // Гараар бичээгүй бол бүртгэлээс өгүүлбэрийг нь бүрдүүлнэ.
            'certificateText' => AssignmentSheet::certificateText($assignment),
            // Хуудасны хэмжээ, зай, фонтыг «Бичиг хэргийн стандарт»-аас авна.
            'format' => $this->pageFormat(),
        ]);
    }

    /**
     * Үнэмлэх дээр гарах дугаар — бүртгэлийн Д/д.
     *
     * Хүснэгтэд мөрүүд нь шинээсээ хуучин руу дугаарлагддаг тул тухайн
     * хэсэгт өөрөөс нь өмнө бүртгэгдсэн мөрүүдийн тоо нь Д/д болно.
     */
    private function rowNumber(TravelAssignment $assignment): int
    {
        return TravelAssignment::query()
            ->where('approver', $assignment->approver)
            ->where('id', '<=', $assignment->id)
            ->count();
    }

    /**
     * Албан бичгийн стандартын хэмжээс.
     *
     * Тохируулаагүй бол MNS 5140-ийн нийтлэг утгаар (A4, зүүн 30мм,
     * баруун 10мм, дээд/доод 20мм) явна.
     *
     * @return array<string, mixed>
     */
    private function pageFormat(): array
    {
        $format = \App\Models\DocumentFormat::defaultFormat();

        return [
            'width' => (float) ($format->width_mm ?? 210),
            'height' => (float) ($format->height_mm ?? 297),
            'top' => (float) ($format->margin_top_mm ?? 20),
            'right' => (float) ($format->margin_right_mm ?? 10),
            'bottom' => (float) ($format->margin_bottom_mm ?? 20),
            'left' => (float) ($format->margin_left_mm ?? 30),
            'font' => $format->font_name ?: 'Times New Roman',
            'size' => (float) ($format->font_size_pt ?? 12),
            'spacing' => (float) ($format->line_spacing ?? 1.4),
        ];
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
