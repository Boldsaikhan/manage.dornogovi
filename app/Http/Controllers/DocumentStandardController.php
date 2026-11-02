<?php

namespace App\Http\Controllers;

use App\Models\DocumentFormat;
use App\Models\DocumentStandard;
use App\Models\DocumentStandardCategory;
use App\Support\DocumentStandardChecker;
use App\Support\ModuleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentStandardController extends Controller
{
    private const MODULE = 'doc_standards';

    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString() ?: 'current';
        $q = trim($request->string('q')->toString());

        $standards = DocumentStandard::query()
            ->with('category:id,key,label,parent_id')
            ->when($status === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->when($status !== 'archived', fn ($query) => $query->whereNull('archived_at'))
            ->when($status === 'current', fn ($query) => $query->whereIn('status', [
                DocumentStandard::STATUS_IN_FORCE,
                DocumentStandard::STATUS_REFERENCED,
            ]))
            ->when($status === 'repealed', fn ($query) => $query->where('status', DocumentStandard::STATUS_REPEALED))
            ->when($category !== '', function ($query) use ($category) {
                $node = DocumentStandardCategory::query()->where('key', $category)->first();
                if (! $node) {
                    $query->whereRaw('0 = 1');

                    return;
                }
                $ids = DocumentStandardCategory::query()
                    ->where('id', $node->id)
                    ->orWhere('parent_id', $node->id)
                    ->pluck('id');
                $query->whereIn('category_id', $ids);
            })
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('title', 'like', $like)
                        ->orWhere('summary', 'like', $like)
                        ->orWhere('body', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentStandard $row) => $row->toCard());

        return Inertia::render('Modules/DocStandards', [
            'formats' => $this->formats(),
            'categories' => DocumentStandardCategory::query()->orderBy('sort_order')->get(['id', 'parent_id', 'key', 'label', 'summary']),
            'standards' => $standards,
            'filters' => ['q' => $q, 'category' => $category, 'status' => $status],
            'canManage' => ModuleAccess::canManage($request->user(), self::MODULE),
        ]);
    }

    public function show(Request $request, DocumentStandard $standard): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);
        $standard->load('category:id,key,label');

        $related = DocumentStandard::query()
            ->where('category_id', $standard->category_id)
            ->where('id', '!=', $standard->id)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DocumentStandard $row) => $row->toCard());

        return Inertia::render('Modules/DocStandards/Show', [
            'standard' => $standard->toDetail(),
            'related' => $related,
            'categories' => DocumentStandardCategory::query()->orderBy('sort_order')->get(['id', 'parent_id', 'key', 'label']),
            'formats' => $standard->slug === 'page-unit' ? $this->formats() : [],
            'canManage' => ModuleAccess::canManage($request->user(), self::MODULE),
        ]);
    }

    public function check(Request $request, DocumentStandardChecker $checker): JsonResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:20000'],
            'kind' => ['nullable', 'string', 'max:32'],
            'slug' => ['nullable', 'string', 'max:80'],
        ]);

        return response()->json($checker->inspect($data['text'], $data['kind'] ?? null, $data['slug'] ?? null));
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

        return back(303)->with('success', $format->label.' хэвлэх тохиргоо шинэчлэгдлээ.');
    }

    public function setDefaultFormat(Request $request, DocumentFormat $format): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        DocumentFormat::query()->update(['is_default' => false]);
        $format->update(['is_default' => true]);

        return back(303)->with('success', $format->label.' үндсэн хэвлэх тохиргоо боллоо.');
    }

    public function storeStandard(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        DocumentStandard::create($this->validated($request) + ['is_official' => false]);

        return back(303)->with('success', 'Стандарт нэмэгдлээ.');
    }

    public function updateStandard(Request $request, DocumentStandard $standard): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $standard->update($this->validated($request, $standard));

        return back(303)->with('success', 'Стандарт шинэчлэгдлээ.');
    }

    public function archiveStandard(Request $request, DocumentStandard $standard): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $archiving = $standard->archived_at === null;
        $standard->update([
            'archived_at' => $archiving ? now() : null,
        ]);

        return back(303)->with('success', $archiving ? 'Архивлалаа.' : 'Архиваас гаргалаа.');
    }

    public function destroyStandard(Request $request, DocumentStandard $standard): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);
        abort_if($standard->is_official, 422, 'Албан эхийг устгахгүй. Архивлана.');

        $standard->delete();

        return back(303)->with('success', 'Устгалаа.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?DocumentStandard $current = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:document_standard_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:20000'],
            'source_title' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'source_number' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in([
                DocumentStandard::STATUS_IN_FORCE,
                DocumentStandard::STATUS_REFERENCED,
                DocumentStandard::STATUS_REPEALED,
            ])],
            'effective_on' => ['nullable', 'date'],
            'amended_on' => ['nullable', 'date'],
            'repealed_on' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'correct_example' => ['nullable', 'string', 'max:4000'],
            'incorrect_example' => ['nullable', 'string', 'max:4000'],
            'example_clause' => ['nullable', 'string', 'max:40'],
        ]);

        $examples = $current?->examples ?? [];
        if (($data['correct_example'] ?? '') !== '' || ($data['incorrect_example'] ?? '') !== '') {
            $examples = array_values(array_filter([
                ($data['correct_example'] ?? '') !== '' ? [
                    'ok' => true,
                    'clause' => $data['example_clause'] ?? '',
                    'text' => $data['correct_example'],
                    'note' => '',
                ] : null,
                ($data['incorrect_example'] ?? '') !== '' ? [
                    'ok' => false,
                    'clause' => $data['example_clause'] ?? '',
                    'text' => $data['incorrect_example'],
                    'note' => '',
                ] : null,
            ]));
        }

        unset($data['correct_example'], $data['incorrect_example'], $data['example_clause']);

        return $data + [
            'examples' => $examples,
            'sort_order' => $data['sort_order'] ?? 0,
            'slug' => $current?->slug ?: 'local-'.bin2hex(random_bytes(4)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formats(): array
    {
        return DocumentFormat::query()->orderBy('id')->get()->map(fn (DocumentFormat $f) => [
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
        ])->all();
    }
}
