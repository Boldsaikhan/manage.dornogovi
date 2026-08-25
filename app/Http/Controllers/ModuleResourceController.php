<?php

namespace App\Http\Controllers;

use App\Models\PhoneDirectoryEntry;
use App\Support\ModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ModuleResourceController extends Controller
{
    public function index(Request $request): Response
    {
        $module = $this->moduleFromRequest($request);
        $config = $this->configOrFail($module);
        $this->authorizeModule($request, $module);

        $modelClass = $config['model'];
        /** @var Model $modelClass */
        $query = $modelClass::query()->latest('id');

        if (method_exists($modelClass, 'user')) {
            $query->with('user:id,name');
        }

        // Хамрах хүрээгээр (агентлаг/сумд/байгууллага) тусад нь бүртгэх — 'all' үед бүгд.
        $scopes = $config['scopes'] ?? [];
        $scopeColumn = $config['scope_column'] ?? 'scope';
        $activeScope = (string) $request->query('scope', 'all');

        if (! $scopes || ! array_key_exists($activeScope, $scopes)) {
            $activeScope = 'all';
        } else {
            $query->where($scopeColumn, $activeScope);
        }

        $scopeTabs = [];
        if ($scopes) {
            $counts = $modelClass::query()
                ->selectRaw("{$scopeColumn} as scope_key, count(*) as aggregate")
                ->groupBy($scopeColumn)
                ->pluck('aggregate', 'scope_key');

            $scopeTabs[] = ['value' => 'all', 'label' => 'Нийт', 'count' => (int) $counts->sum()];
            foreach ($scopes as $value => $label) {
                $scopeTabs[] = ['value' => $value, 'label' => $label, 'count' => (int) ($counts[$value] ?? 0)];
            }
        }

        $rows = $query->limit(200)->get()->map(fn (Model $row) => $this->serialize($row, $config));

        return Inertia::render('Modules/ResourceIndex', [
            'scopeTabs' => $scopeTabs,
            'activeScope' => $activeScope,
            'scopeField' => $scopes ? $scopeColumn : null,
            'module' => $module,
            'title' => $config['title'],
            'description' => $config['description'] ?? '',
            'columns' => $config['columns'],
            'fields' => $config['fields'],
            'directory' => $this->directoryFor($config),
            'rows' => $rows,
            'canManage' => ModuleAccess::canManage($request->user(), $module),
            'storeUrl' => route('modules.store', $module),
            'destroyUrlTemplate' => url('/modules/'.$module).'/{id}',
        ]);
    }

    public function store(Request $request, string $module): RedirectResponse
    {
        $config = $this->configOrFail($module);
        abort_unless(ModuleAccess::canManage($request->user(), $module), 403);

        $data = $this->validated($request, $config);
        $data = array_merge($config['defaults'] ?? [], $data);
        $data = $this->applyCreateHooks($request, $config, $data);

        $config['model']::create($data);

        return back()->with('success', 'Амжилттай хадгаллаа.');
    }

    public function destroy(Request $request, string $module, int $id): RedirectResponse
    {
        $config = $this->configOrFail($module);
        abort_unless(ModuleAccess::canManage($request->user(), $module), 403);

        $config['model']::query()->whereKey($id)->delete();

        return back()->with('success', 'Устгалаа.');
    }

    private function moduleFromRequest(Request $request): string
    {
        $path = trim($request->path(), '/');
        $module = str_contains($path, '/') ? substr($path, strrpos($path, '/') + 1) : $path;

        return $module;
    }

    private function configOrFail(string $module): array
    {
        $config = config("module_resources.{$module}");
        abort_unless(is_array($config), 404);

        return $config;
    }

    private function authorizeModule(Request $request, string $module): void
    {
        abort_unless(ModuleAccess::canView($request->user(), $module), 403);
    }

    private function validated(Request $request, array $config): array
    {
        $rules = [];
        foreach ($config['fields'] as $field) {
            $name = $field['name'];
            $rule = [];
            $rule[] = ! empty($field['required']) ? 'required' : 'nullable';

            $rule[] = match ($field['type'] ?? 'text') {
                'number' => 'integer',
                'date' => 'date',
                'datetime' => 'date',
                'checkbox' => 'boolean',
                'select' => Rule::in(array_keys($field['options'] ?? [])),
                'textarea', 'text', 'directory_org', 'directory_person' => 'string',
                default => 'string',
            };

            $rules[$name] = $rule;
        }

        $data = $request->validate($rules);

        foreach ($config['fields'] as $field) {
            if (($field['type'] ?? '') === 'checkbox') {
                $data[$field['name']] = $request->boolean($field['name']);
            }
        }

        return $data;
    }

    private function applyCreateHooks(Request $request, array $config, array $data): array
    {
        $user = $request->user();

        return match ($config['on_create'] ?? null) {
            'attach_user_department' => array_merge($data, [
                'user_id' => $user->id,
                'department_id' => $user->department_id,
            ]),
            'attach_creator' => array_merge($data, [
                'created_by' => $user->id,
            ]),
            'attach_issuer' => array_merge($data, [
                'issued_by' => $user->id,
                'issued_on' => $data['issued_on'] ?? Carbon::today()->toDateString(),
            ]),
            'attach_creator_department' => array_merge($data, [
                'created_by' => $user->id,
                'department_id' => $data['department_id'] ?? $user->department_id,
            ]),
            default => $data,
        };
    }

    /**
     * Утасны жагсаалтад бүртгэлтэй байгууллага, хүмүүсийг сонголт болгож дамжуулна.
     *
     * @return array<int, array<string, mixed>>
     */
    private function directoryFor(array $config): array
    {
        $needed = collect($config['fields'] ?? [])
            ->contains(fn (array $f) => in_array($f['type'] ?? '', ['directory_org', 'directory_person'], true));

        if (! $needed) {
            return [];
        }

        return PhoneDirectoryEntry::query()
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['org_name', 'person_name', 'position'])
            ->groupBy('org_name')
            ->map(fn ($rows, $orgName) => [
                'org_name' => $orgName,
                'people' => $rows->map(fn (PhoneDirectoryEntry $row) => [
                    'name' => $row->person_name,
                    'position' => $row->position,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function serialize(Model $row, array $config): array
    {
        $out = ['id' => $row->getKey()];

        foreach ($config['columns'] as $col) {
            $key = $col['key'];
            $out[$key] = match (true) {
                $key === ($config['scope_column'] ?? 'scope') && ! empty($config['scopes'])
                    => $config['scopes'][$row->{$key}] ?? ($row->{$key} ?? '—'),
                default => $this->serializeValue($row, $key),
            };
        }

        return $out;
    }

    private function serializeValue(Model $row, string $key): string
    {
        return match ($key) {
            'user_name' => $row->user->name ?? '—',
            'person_label' => $row->person_name ?: ($row->user->name ?? '—'),
            'kind_label' => method_exists($row, 'kindLabel') ? $row->kindLabel() : ($row->kind ?? '—'),
            'for_new_hires' => $row->for_new_hires ? 'Тийм' : 'Үгүй',
            'published_at', 'held_at', 'start_date', 'end_date', 'issued_on', 'due_on' => optional($row->{$key})->format(
                str_contains($key, 'held') ? 'Y-m-d H:i' : 'Y-m-d'
            ) ?? '—',
            default => (string) ($row->{$key} ?? '—'),
        };
    }
}
