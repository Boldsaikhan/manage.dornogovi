<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\System;
use App\Services\Ai\AiSettings;
use App\Services\EmbedChecker;
use App\Support\ModuleAccess;
use App\Support\ModuleVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function index(AiSettings $aiSettings): Response
    {
        return Inertia::render('Admin/Systems', [
            'systems' => System::orderBy('sort_order')->orderBy('name')->get()->map(fn (System $system) => [
                'id' => $system->id,
                'name' => $system->name,
                'url' => $system->url,
                'login_url' => $system->login_url,
                'login_method' => $system->login_method,
                'login_form_action' => $system->login_form_action,
                'login_username_field' => $system->login_username_field,
                'login_password_field' => $system->login_password_field,
                'is_active' => $system->is_active,
                'requires_login' => $system->requires_login,
                'is_internal' => $system->is_internal,
                'is_embeddable' => $system->is_embeddable,
                'can_auto_submit' => $system->canAutoSubmit(),
            ]),
            'ai' => $aiSettings->forAdmin(),
            'menus' => ModuleVisibility::forAdmin(),
        ]);
    }

    public function updateMenus(Request $request): RedirectResponse
    {
        $allowed = ModuleAccess::definitions()->pluck('key')->all();

        $data = $request->validate([
            'enabled' => ['required', 'array'],
            'enabled.*' => ['boolean'],
        ]);

        $disabled = [];
        foreach ($allowed as $key) {
            // Checkbox илгээгдээгүй эсвэл false бол хаасан гэж үзнэ.
            if (empty($data['enabled'][$key])) {
                $disabled[] = $key;
            }
        }

        ModuleVisibility::setDisabled($disabled);

        return back()->with('success', 'Цэсийн нээлттэй/хаалттай тохиргоо хадгалагдлаа.');
    }

    public function updateAi(Request $request, AiSettings $aiSettings): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'provider' => ['required', Rule::in(['local', 'openai'])],
            'openai_model' => ['required', 'string', 'max:100'],
            'daily_question_limit' => ['required', 'integer', 'min:0', 'max:1000'],
            'openai_api_key' => ['nullable', 'string', 'max:500'],
            'clear_api_key' => ['boolean'],
        ]);

        $aiSettings->set(AiSettings::KEY_ENABLED, ! empty($data['enabled']) ? '1' : '0');
        $aiSettings->set(AiSettings::KEY_PROVIDER, $data['provider']);
        $aiSettings->set(AiSettings::KEY_OPENAI_MODEL, $data['openai_model']);
        $aiSettings->set(AiSettings::KEY_DAILY_LIMIT, (string) $data['daily_question_limit']);

        if (! empty($data['clear_api_key'])) {
            $aiSettings->setOpenAiApiKey(null);
        } elseif (! empty($data['openai_api_key'])) {
            $aiSettings->setOpenAiApiKey($data['openai_api_key']);
        }

        return back()->with('success', 'AI туслахын тохиргоо хадгалагдлаа.');
    }

    public function update(Request $request, System $system): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2000'],
            'login_url' => ['nullable', 'url', 'max:2000'],
            'login_method' => ['required', 'in:'.System::LOGIN_MANUAL.','.System::LOGIN_FORM_POST],
            'login_form_action' => ['nullable', 'url', 'max:2000', 'required_if:login_method,'.System::LOGIN_FORM_POST],
            'login_username_field' => ['nullable', 'string', 'max:100', 'required_if:login_method,'.System::LOGIN_FORM_POST],
            'login_password_field' => ['nullable', 'string', 'max:100', 'required_if:login_method,'.System::LOGIN_FORM_POST],
            'is_active' => ['boolean'],
            'requires_login' => ['boolean'],
            'is_internal' => ['boolean'],
        ]);

        $system->update($data);

        return back()->with('success', "\"{$system->name}\" тохиргоо хадгалагдлаа.");
    }

    public function checkEmbed(System $system, EmbedChecker $checker): RedirectResponse
    {
        $checker->refresh($system);

        return back()->with('success', $system->is_embeddable
            ? "\"{$system->name}\" дотор нээгдэх боломжтой."
            : "\"{$system->name}\" дотор нээгдэхгүй: ".($system->embed_blocked_by ?? 'тодорхойгүй'));
    }
}
