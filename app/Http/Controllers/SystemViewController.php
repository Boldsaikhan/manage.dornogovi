<?php

namespace App\Http\Controllers;

use App\Models\System;
use Inertia\Inertia;
use Inertia\Response;

class SystemViewController extends Controller
{
    /**
     * Дотор нь нээгдэхийг зөвшөөрдөг системийг iframe-д харуулна.
     */
    public function show(System $system): Response
    {
        abort_unless($system->is_active, 404);

        return Inertia::render('Systems/View', [
            'system' => [
                'id' => $system->id,
                'name' => $system->name,
                'icon' => $system->icon,
                'entry_url' => $system->entryUrl(),
                'is_embeddable' => (bool) $system->is_embeddable,
                'embed_blocked_by' => $system->embed_blocked_by,
            ],
            'target' => $system->entryUrl(),
        ]);
    }
}
