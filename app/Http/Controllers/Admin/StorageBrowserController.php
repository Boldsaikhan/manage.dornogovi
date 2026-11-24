<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\StorageBrowser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Системийн тохиргоо → Файлууд — хавтас/зураг үзэх.
 */
class StorageBrowserController extends Controller
{
    public function browse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'root' => ['required', 'string', 'max:40'],
            'path' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $listing = StorageBrowser::browse($data['root'], $data['path'] ?? '');
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return response()->json([
            'roots' => StorageBrowser::rootOptions(),
            ...$listing,
        ]);
    }

    public function preview(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'root' => ['required', 'string', 'max:40'],
            'path' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $absolute = StorageBrowser::absoluteFile($data['root'], $data['path']);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        $images = array_map('strtolower', config('storage_browser.image_extensions', []));
        abort_unless(in_array($extension, $images, true), 404);

        return response()->file($absolute, [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'root' => ['required', 'string', 'max:40'],
            'path' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $absolute = StorageBrowser::absoluteFile($data['root'], $data['path']);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return response()->download($absolute, basename($absolute));
    }
}
