<?php

namespace App\Http\Controllers;

use App\Models\DocumentFormat;
use App\Models\DocumentStandard;
use App\Support\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentStandardController extends Controller
{
    private const MODULE = 'doc_standards';

    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        return Inertia::render('Modules/DocStandards', [
            'formats' => DocumentFormat::query()->orderBy('id')->get()->map(fn (DocumentFormat $f) => [
                'id' => $f->id,
                'key' => $f->key,
                'label' => $f->label,
                'width_mm' => $f->width_mm,
                'height_mm' => $f->height_mm,
                'margin_top_mm' => $f->margin_top_mm,
                'margin_right_mm' => $f->margin_right_mm,
                'margin_bottom_mm' => $f->margin_bottom_mm,
                'margin_left_mm' => $f->margin_left_mm,
                'font_name' => $f->font_name,
                'font_size_pt' => $f->font_size_pt,
                'line_spacing' => $f->line_spacing,
                'is_default' => $f->is_default,
            ]),
            'standards' => DocumentStandard::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'title', 'body', 'sort_order']),
            'canManage' => ModuleAccess::canManage($request->user(), self::MODULE),
        ]);
    }

    public function updateFormat(Request $request, DocumentFormat $format): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'width_mm' => ['required', 'integer', 'between:50,600'],
            'height_mm' => ['required', 'integer', 'between:50,900'],
            'margin_top_mm' => ['required', 'integer', 'between:0,100'],
            'margin_right_mm' => ['required', 'integer', 'between:0,100'],
            'margin_bottom_mm' => ['required', 'integer', 'between:0,100'],
            'margin_left_mm' => ['required', 'integer', 'between:0,100'],
            'font_name' => ['required', 'string', 'max:64'],
            'font_size_pt' => ['required', 'numeric', 'between:6,36'],
            'line_spacing' => ['required', 'numeric', 'between:1,3'],
        ]);

        $format->update($data);

        return back(303)->with('success', $format->label.' стандарт шинэчлэгдлээ.');
    }

    public function setDefaultFormat(Request $request, DocumentFormat $format): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        DocumentFormat::query()->update(['is_default' => false]);
        $format->update(['is_default' => true]);

        return back(303)->with('success', $format->label.' үндсэн стандарт боллоо.');
    }

    public function storeStandard(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
        ]);

        DocumentStandard::create($data + ['sort_order' => $data['sort_order'] ?? 0]);

        return back(303)->with('success', 'Заавар нэмэгдлээ.');
    }

    public function destroyStandard(Request $request, DocumentStandard $standard): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $standard->delete();

        return back(303)->with('success', 'Устгалаа.');
    }
}
