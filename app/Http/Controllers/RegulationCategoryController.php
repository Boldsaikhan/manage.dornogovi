<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Support\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegulationCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'regulations'), 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
        ]);

        $label = trim($data['label']);

        if (RegulationCategory::query()->where('label', $label)->exists()) {
            return back()->with('warning', 'Ийм нэртэй таб аль хэдийн байна.');
        }

        $category = RegulationCategory::query()->create([
            'key' => RegulationCategory::keyFor($label),
            'label' => $label,
            'sort_order' => (int) RegulationCategory::query()->max('sort_order') + 1,
        ]);

        return redirect()
            ->route('regulations.index', ['scope' => $category->key])
            ->with('success', sprintf('«%s» таб нэмэгдлээ.', $category->label));
    }

    public function update(Request $request, RegulationCategory $category): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'regulations'), 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $label = trim($data['label']);

        if (RegulationCategory::query()->where('label', $label)->where('id', '!=', $category->id)->exists()) {
            return back()->with('warning', 'Ийм нэртэй таб аль хэдийн байна.');
        }

        $category->update([
            'label' => $label,
            'sort_order' => array_key_exists('sort_order', $data)
                ? (int) $data['sort_order']
                : $category->sort_order,
        ]);

        return back()->with('success', 'Таб шинэчлэгдлээ.');
    }

    public function destroy(Request $request, RegulationCategory $category): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), 'regulations'), 403);

        $count = Regulation::query()->where('category', $category->key)->count();
        if ($count > 0) {
            return back()->with('warning', sprintf('Энэ табт %d бүртгэл байна. Эхлээд устгана уу.', $count));
        }

        $fallback = RegulationCategory::query()
            ->where('id', '!=', $category->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        abort_unless($fallback, 403, 'Сүүлийн табыг устгах боломжгүй.');

        $label = $category->label;
        $category->delete();

        return redirect()
            ->route('regulations.index', ['scope' => $fallback->key])
            ->with('success', sprintf('«%s» таб устгагдлаа.', $label));
    }
}
