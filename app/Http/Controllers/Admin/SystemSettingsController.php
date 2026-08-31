<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\System;
use App\Models\User;
use App\Services\Ai\AiSettings;
use App\Services\EmbedChecker;
use App\Support\ModuleAccess;
use App\Support\ModuleOrder;
use App\Support\ModuleVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function index(AiSettings $aiSettings): Response
    {
        return Inertia::render('Admin/Systems', [
            'systems' => System::with('viewers:id')->orderBy('sort_order')->orderBy('id')->get()->map(fn (System $system) => [
                'id' => $system->id,
                'name' => $system->name,
                'url' => $system->url,
                'login_url' => $system->login_url,
                'login_method' => $system->login_method,
                'supports_dan' => (bool) $system->supports_dan,
                'dan_login_url' => $system->dan_login_url,
                'login_form_action' => $system->login_form_action,
                'login_username_field' => $system->login_username_field,
                'login_password_field' => $system->login_password_field,
                'is_active' => $system->is_active,
                'requires_login' => $system->requires_login,
                'is_internal' => $system->is_internal,
                'is_embeddable' => $system->is_embeddable,
                'sort_order' => (int) $system->sort_order,
                'can_auto_submit' => $system->canAutoSubmit(),
                // Хоосон жагсаалт = бүх албан хаагчид харна.
                'viewer_ids' => $system->viewers->pluck('id')->all(),
            ]),
            'employees' => User::query()
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'position', 'phone', 'department_id'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'position' => $u->position,
                    'phone' => $u->phone,
                    'department' => $u->department?->name,
                ]),
            'ai' => $aiSettings->forAdmin(),
            'menus' => ModuleVisibility::forAdmin(),
            'menuGroups' => ModuleVisibility::groupsForAdmin(),
            // AI аль цэсэд ямар эрхтэйг тохируулах жагсаалт.
            'aiModules' => $this->aiModules($aiSettings),
        ]);
    }

    /**
     * Manage AI-ийн хандах боломжтой цэсүүд, одоогийн түвшин.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aiModules(AiSettings $aiSettings): array
    {
        $groups = config('modules.groups', []);

        $rows = [[
            'key' => AiSettings::GENERAL_MODULE,
            'label' => 'Ерөнхий (самбар, ажилтны хайлт)',
            'group' => 'Ерөнхий',
            'level' => $aiSettings->accessFor(AiSettings::GENERAL_MODULE),
        ]];

        foreach (ModuleAccess::definitions() as $item) {
            $rows[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'group' => $groups[$item['group']] ?? $item['group'],
                'level' => $aiSettings->accessFor($item['key']),
            ];
        }

        return $rows;
    }

    public function updateMenus(Request $request): RedirectResponse
    {
        $allowed = ModuleAccess::definitions()->pluck('key')->all();

        $data = $request->validate([
            'enabled' => ['required', 'array'],
            'enabled.*' => ['boolean'],
            'group_order' => ['nullable', 'array'],
            'group_order.*' => ['string'],
            'item_order' => ['nullable', 'array'],
            'item_order.*' => ['string'],
        ]);

        $disabled = [];
        foreach ($allowed as $key) {
            // Checkbox илгээгдээгүй эсвэл false бол хаасан гэж үзнэ.
            if (empty($data['enabled'][$key])) {
                $disabled[] = $key;
            }
        }

        ModuleVisibility::setDisabled($disabled);

        if (array_key_exists('group_order', $data) && array_key_exists('item_order', $data)) {
            ModuleOrder::setOrder(
                is_array($data['group_order']) ? $data['group_order'] : [],
                is_array($data['item_order']) ? $data['item_order'] : [],
            );
        }

        return redirect()
            ->route('admin.systems.index', ['tab' => 'menus'])
            ->with('success', 'Цэсийн тохиргоо хадгалагдлаа.');
    }

    public function updateAi(Request $request, AiSettings $aiSettings): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'display_name' => ['required', 'string', 'max:80'],
            'provider' => ['required', Rule::in(['local', 'openai'])],
            'openai_model' => ['required', 'string', 'max:100'],
            'daily_question_limit' => ['required', 'integer', 'min:0', 'max:1000'],
            'openai_api_key' => ['nullable', 'string', 'max:500'],
            'module_access' => ['nullable', 'array'],
            'module_access.*' => [Rule::in(array_keys(AiSettings::ACCESS_LABELS))],
            'clear_api_key' => ['boolean'],
        ]);

        $aiSettings->set(AiSettings::KEY_ENABLED, ! empty($data['enabled']) ? '1' : '0');
        $aiSettings->set(AiSettings::KEY_DISPLAY_NAME, trim($data['display_name']));
        $aiSettings->set(AiSettings::KEY_PROVIDER, $data['provider']);
        $aiSettings->set(AiSettings::KEY_OPENAI_MODEL, $data['openai_model']);
        $aiSettings->set(AiSettings::KEY_DAILY_LIMIT, (string) $data['daily_question_limit']);
        $aiSettings->setModuleAccess($data['module_access'] ?? []);

        if (! empty($data['clear_api_key'])) {
            $aiSettings->setOpenAiApiKey(null);
        } elseif (! empty($data['openai_api_key'])) {
            $aiSettings->setOpenAiApiKey($data['openai_api_key']);
        }

        return redirect()
            ->route('admin.systems.index', ['tab' => 'ai'])
            ->with('success', 'Manage AI тохиргоо хадгалагдлаа.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedSystem($request);

        $baseSlug = Str::slug($data['name']) ?: 'system';
        $slug = $baseSlug;
        $i = 2;
        while (System::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        $maxOrder = (int) System::query()->max('sort_order');

        $viewerIds = $data['viewer_ids'] ?? [];
        unset($data['viewer_ids']);

        $system = System::query()->create([
            ...$data,
            'slug' => $slug,
            'sort_order' => $maxOrder + 1,
            'category' => $data['is_internal'] ? 'Дотоод' : 'Гадны',
            'icon' => 'globe',
        ]);

        $system->viewers()->sync($viewerIds);

        return redirect()
            ->route('admin.systems.index', ['tab' => 'systems', 'system' => $system->id])
            ->with('success', "\"{$system->name}\" систем бүртгэгдлээ.");
    }

    public function update(Request $request, System $system): RedirectResponse
    {
        $data = $this->validatedSystem($request);

        $viewerIds = $data['viewer_ids'] ?? [];
        unset($data['viewer_ids']);

        $system->update($data);
        $system->viewers()->sync($viewerIds);

        $who = $viewerIds === []
            ? 'цэсэнд харагдахгүй'
            : count($viewerIds).' албан хаагчийн цэсэнд харагдана';

        return redirect()
            ->route('admin.systems.index', ['tab' => 'systems', 'system' => $system->id])
            ->with('success', "\"{$system->name}\" тохиргоо хадгалагдлаа — {$who}.");
    }

    public function updateViewers(Request $request, System $system): RedirectResponse
    {
        $data = $request->validate([
            'viewer_ids' => ['present', 'array'],
            'viewer_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $ids = array_values(array_unique($data['viewer_ids']));
        $system->viewers()->sync($ids);

        $who = $ids === []
            ? 'цэсэнд харагдахгүй'
            : count($ids).' албан хаагчийн цэсэнд харагдана';

        return redirect()
            ->route('admin.systems.index', ['tab' => 'systems', 'system' => $system->id])
            ->with('success', "\"{$system->name}\" — {$who}.");
    }

    public function destroy(System $system): RedirectResponse
    {
        $name = $system->name;
        $system->delete();

        return back()->with('success', "\"{$name}\" систем устгагдлаа.");
    }

    /**
     * Цэсэнд («Холбосон системүүд») харагдах дарааллыг шинэчилнэ.
     * Зөвхөн гадаад (is_internal=false) системүүдийн дарааллыг тохируулна.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:systems,id'],
        ]);

        $ids = array_values($data['ids']);

        // Цэсэнд гарах системүүд эхэнд — 1..n
        foreach ($ids as $index => $id) {
            System::query()
                ->whereKey($id)
                ->where('is_internal', false)
                ->update(['sort_order' => $index + 1]);
        }

        // Дотоод системүүдийг цэсийн дараа байрлуулна.
        $next = count($ids) + 1;
        System::query()
            ->where('is_internal', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (System $system) use (&$next) {
                $system->update(['sort_order' => $next]);
                $next++;
            });

        return back()->with('success', 'Цэсийн дараалал хадгалагдлаа.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSystem(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2000'],
            'login_url' => ['nullable', 'url', 'max:2000'],
            'login_method' => ['required', 'in:'.System::LOGIN_MANUAL.','.System::LOGIN_FORM_POST],
            'login_form_action' => ['nullable', 'url', 'max:2000', 'required_if:login_method,'.System::LOGIN_FORM_POST],
            'login_username_field' => ['nullable', 'string', 'max:100', 'required_if:login_method,'.System::LOGIN_FORM_POST],
            'login_password_field' => ['nullable', 'string', 'max:100', 'required_if:login_method,'.System::LOGIN_FORM_POST],
            'supports_dan' => ['boolean'],
            'dan_login_url' => ['nullable', 'url', 'max:2000'],
            'is_active' => ['boolean'],
            'requires_login' => ['boolean'],
            'is_internal' => ['boolean'],
            'viewer_ids' => ['nullable', 'array'],
            'viewer_ids.*' => ['integer', 'exists:users,id'],
        ]);
    }

    public function checkEmbed(System $system, EmbedChecker $checker): RedirectResponse
    {
        $checker->refresh($system);

        return back()->with('success', $system->is_embeddable
            ? "\"{$system->name}\" дотор нээгдэх боломжтой."
            : "\"{$system->name}\" дотор нээгдэхгүй: ".($system->embed_blocked_by ?? 'тодорхойгүй'));
    }
}
