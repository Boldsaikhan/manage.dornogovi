<?php

namespace App\Http\Middleware;

use App\Models\System;
use App\Models\UserCredential;
use App\Services\Ai\AiSettings;
use App\Support\ModuleAccess;
use App\Support\NavBadges;
use App\Support\Vault;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->loadMissing('department:id,name'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'info' => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'vault' => fn () => [
                'unlocked' => Vault::isUnlocked($request),
                'until' => Vault::unlockedUntil($request),
            ],
            'nav' => fn () => $this->navigation($request),
            'moduleNav' => fn () => ModuleAccess::navFor($request->user()),
            'navBadges' => fn () => NavBadges::for($request->user()),
            'aiAssistant' => fn () => $this->aiAssistantMeta($request),
            'systemsHubEnabled' => fn () => ModuleAccess::canView($request->user(), 'systems'),
        ];
    }

    /**
     * @return array{available: bool}|null
     */
    private function aiAssistantMeta(Request $request): ?array
    {
        $user = $request->user();
        if (! $user || ! ModuleAccess::canView($user, 'ai')) {
            return null;
        }

        $settings = app(AiSettings::class);

        return [
            'available' => $settings->enabled(),
            'href' => route('ai.index'),
            'name' => $settings->displayName(),
        ];
    }

    /**
     * Хажуугийн самбарт харагдах системүүд.
     *
     * @return array<int, array<string, mixed>>
     */
    private function navigation(Request $request): array
    {
        if (! $request->user()) {
            return [];
        }

        $saved = UserCredential::where('user_id', $request->user()->id)
            ->pluck('system_id')
            ->all();

        return System::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (System $system) => [
                'id' => $system->id,
                'name' => $system->name,
                'icon' => $system->icon,
                'entry_url' => $system->entryUrl(),
                'is_embeddable' => (bool) $system->is_embeddable,
                'is_internal' => (bool) $system->is_internal,
                'requires_login' => (bool) $system->requires_login,
                'has_credential' => in_array($system->id, $saved, true),
            ])
            ->values()
            ->all();
    }
}
