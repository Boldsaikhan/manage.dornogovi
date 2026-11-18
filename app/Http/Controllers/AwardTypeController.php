<?php

namespace App\Http\Controllers;

use App\Models\AwardType;
use App\Support\AwardNameNormalizer;
use App\Support\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AwardTypeController extends Controller
{
    private const MODULE = 'awards';

    public function store(Request $request, AwardNameNormalizer $names): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        $request->merge([
            'name' => $names->canonicalize((string) $request->input('name')) ?? trim((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:award_types,name'],
        ]);

        $max = (int) AwardType::query()->max('sort_order');

        AwardType::query()->create([
            'name' => $data['name'],
            'sort_order' => $max + 10,
            'is_active' => true,
        ]);

        return back()->with('success', 'Шагналын төрөл нэмэгдлээ.');
    }

    public function update(Request $request, AwardType $awardType, AwardNameNormalizer $names): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        if ($request->exists('name')) {
            $request->merge([
                'name' => $names->canonicalize((string) $request->input('name')) ?? trim((string) $request->input('name')),
            ]);
        }

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('award_types', 'name')->ignore($awardType->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data) && $data['name'] !== $awardType->name && $awardType->isInUse()) {
            return back()->withErrors([
                'name' => 'Энэ төрөл шагналын бүртгэлд ашиглагдаж байгаа тул нэрийг солих боломжгүй.',
            ]);
        }

        $awardType->update($data);

        return back()->with('success', 'Шагналын төрөл шинэчлэгдлээ.');
    }

    public function destroy(Request $request, AwardType $awardType): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);

        if ($awardType->isInUse()) {
            return back()->withErrors([
                'name' => 'Энэ төрөл шагналын бүртгэлд ашиглагдаж байгаа тул устгах боломжгүй.',
            ]);
        }

        $awardType->delete();

        return back()->with('success', 'Шагналын төрөл устгалаа.');
    }
}
