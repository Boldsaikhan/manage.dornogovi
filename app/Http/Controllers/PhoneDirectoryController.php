<?php

namespace App\Http\Controllers;

use App\Models\PhoneDirectoryEntry;
use App\Support\ModuleAccess;
use App\Support\PhoneDirectoryDocxParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class PhoneDirectoryController extends Controller
{
    private const MODULE = 'phone_directory';

    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $entries = PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $groups = $entries
            ->groupBy('org_name')
            ->map(fn ($rows, $orgName) => [
                'org_name' => $orgName,
                'rows' => $rows->map(fn (PhoneDirectoryEntry $row) => [
                    'id' => $row->id,
                    'person_name' => $row->person_name,
                    'position' => $row->position,
                    'office_phone' => $row->office_phone,
                    'mobile_phone' => $row->mobile_phone,
                ])->values(),
            ])
            ->values();

        return Inertia::render('Modules/PhoneDirectory', [
            'groups' => $groups,
            'total' => $entries->count(),
            'orgNames' => $entries->pluck('org_name')->unique()->values(),
            'canManage' => ModuleAccess::canManage($request->user(), self::MODULE),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:255'],
            'person_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'office_phone' => ['nullable', 'string', 'max:64'],
            'mobile_phone' => ['nullable', 'string', 'max:64'],
        ]);

        $sibling = PhoneDirectoryEntry::query()->where('org_name', $data['org_name']);

        $data['org_order'] = (int) ((clone $sibling)->value('org_order')
            ?? (PhoneDirectoryEntry::query()->max('org_order') + 1));
        $data['sort_order'] = (int) (clone $sibling)->max('sort_order') + 1;

        PhoneDirectoryEntry::create($data);

        return back()->with('success', 'Бүртгэл нэмэгдлээ.');
    }

    public function destroy(Request $request, PhoneDirectoryEntry $entry): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $entry->delete();

        return back()->with('success', 'Устгалаа.');
    }

    public function import(Request $request, PhoneDirectoryDocxParser $parser): RedirectResponse
    {
        abort_unless(ModuleAccess::canManage($request->user(), self::MODULE), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'replace' => ['nullable', 'boolean'],
        ]);

        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());

        if (! in_array($extension, ['docx', 'docm'], true)) {
            return back()->withErrors(['file' => 'Зөвхөн .docx файл дэмжинэ. Word дээр «Save as → .docx» хийж оруулна уу.']);
        }

        try {
            $rows = $parser->parse($request->file('file')->getRealPath());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (Throwable) {
            return back()->withErrors(['file' => 'Word файлыг уншиж чадсангүй. .docx хэлбэрээр хадгалж дахин оруулна уу.']);
        }

        if (! $rows) {
            return back()->withErrors(['file' => 'Файлаас хүснэгт олдсонгүй. Толгой нь № / Овог нэр / Албан тушаал / Ажлын өрөөний утас / Гар утас байх ёстой.']);
        }

        $replace = $request->boolean('replace');

        DB::transaction(function () use ($rows, $replace) {
            if ($replace) {
                PhoneDirectoryEntry::query()->delete();
                $offset = 0;
            } else {
                $offset = (int) PhoneDirectoryEntry::query()->max('org_order');
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                $now = now();

                PhoneDirectoryEntry::insert(array_map(fn (array $row) => [
                    'org_name' => $row['org_name'],
                    'org_order' => $row['org_order'] + $offset,
                    'sort_order' => $row['sort_order'],
                    'person_name' => $row['person_name'],
                    'position' => $row['position'],
                    'office_phone' => $row['office_phone'],
                    'mobile_phone' => $row['mobile_phone'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
        });

        return back()->with('success', count($rows).' мөр импортлолоо.');
    }
}
