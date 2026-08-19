<?php

namespace App\Http\Controllers;

use App\Models\System;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $systems = System::query()
            ->where('is_active', true)
            ->with(['credentials' => fn ($q) => $q->where('user_id', $userId)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (System $system) {
                $credential = $system->credentials->first();

                return [
                    'id' => $system->id,
                    'name' => $system->name,
                    'url' => $system->url,
                    'entry_url' => $system->entryUrl(),
                    'description' => $system->description,
                    'category' => $system->category,
                    'icon' => $system->icon,
                    'is_embeddable' => (bool) $system->is_embeddable,
                    'auto_submit' => $system->canAutoSubmit(),
                    'requires_login' => (bool) $system->requires_login,
                    'has_credential' => (bool) $credential,
                    'last_used_at' => $credential?->last_used_at?->diffForHumans(),
                ];
            });

        return Inertia::render('Dashboard', [
            'systems' => $systems,
            'stats' => [
                'total' => $systems->count(),
                'saved' => $systems->where('has_credential', true)->count(),
            ],
            // Хажуугийн самбараас "нэвтрэх" дарахад аль системийг нээхийг заана.
            'focus' => $request->integer('focus') ?: null,
        ]);
    }
}
