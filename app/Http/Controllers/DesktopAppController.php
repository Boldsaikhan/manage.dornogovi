<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Windows ширээний програмын суулгац.
 *
 * Суулгацыг `desktop-app` төслөөс (npm run dist) бэлдээд
 * `storage/app/desktop/` хавтсанд тавина.
 */
class DesktopAppController extends Controller
{
    private const FILE = 'Manage-Dornogovi-Setup.exe';

    public function download(): BinaryFileResponse|RedirectResponse
    {
        $path = storage_path('app/desktop/'.self::FILE);

        if (! is_file($path)) {
            return back()->withErrors([
                'desktop' => 'Суулгац хараахан байршуулаагүй байна. Системийн админд хандана уу.',
            ]);
        }

        return response()->download($path, self::FILE, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
