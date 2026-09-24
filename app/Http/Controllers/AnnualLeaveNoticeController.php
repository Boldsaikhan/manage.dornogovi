<?php

namespace App\Http\Controllers;

use App\Models\AnnualLeave;
use App\Models\DocumentFormat;
use App\Support\AnnualLeaveNotice;
use App\Support\ModuleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «Ээлжийн амралт олгох тухай мэдэгдэл» хэвлэх.
 */
class AnnualLeaveNoticeController extends Controller
{
    private const MODULE = 'annual_leaves';

    public function show(Request $request, AnnualLeave $annualLeave): View
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $copies = (int) $request->query('copies', 2);
        $copies = in_array($copies, [1, 2], true) ? $copies : 2;

        $format = DocumentFormat::defaultFormat();

        return view('annual-leaves.notice', [
            'annualLeave' => $annualLeave,
            'copies' => $copies,
            'text' => AnnualLeaveNotice::text($annualLeave),
            'approverLines' => AnnualLeaveNotice::approverLines($annualLeave),
            'approverName' => AnnualLeaveNotice::approverName($annualLeave),
            'number' => $this->rowNumber($annualLeave),
            'registeredOn' => $annualLeave->created_at,
            'format' => [
                'width' => (float) ($format?->width_mm ?? 210),
                'height' => (float) ($format?->height_mm ?? 297),
                'top' => (float) ($format?->margin_top_mm ?? 20),
                'right' => (float) ($format?->margin_right_mm ?? 10),
                'bottom' => (float) ($format?->margin_bottom_mm ?? 20),
                'left' => (float) ($format?->margin_left_mm ?? 30),
                'font' => $format?->font_name ?: 'Arial',
                'size' => (float) ($format?->font_size_pt ?? 12),
                'spacing' => (float) ($format?->line_spacing ?? 1.4),
            ],
            'canEdit' => ModuleAccess::canEdit($request->user(), self::MODULE),
        ]);
    }

    /**
     * Бүртгэлийн хүснэгтэд харагдах Д/д дугаар.
     *
     * Хүснэгтэд мөрүүд шинээсээ хуучин руу дугаарлагддаг тул тухайн
     * хамрах хүрээнд өөрөөс нь өмнө (id-гаар) бүртгэгдсэн мөрүүдийн тоо
     * нь Д/д болно.
     */
    private function rowNumber(AnnualLeave $annualLeave): int
    {
        return AnnualLeave::query()
            ->where('scope', $annualLeave->scope)
            ->where('id', '<=', $annualLeave->id)
            ->count();
    }

    /** Мэдэгдлийн бичвэрийг хуудсан дээр нь засна. */
    public function updateText(Request $request, AnnualLeave $annualLeave): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'notice_text' => ['nullable', 'string', 'max:2000'],
        ], [], ['notice_text' => 'бичвэр']);

        $annualLeave->update([
            'notice_text' => trim((string) ($data['notice_text'] ?? '')) ?: null,
        ]);

        return back()->with('success', 'Хадгаллаа.');
    }
}
