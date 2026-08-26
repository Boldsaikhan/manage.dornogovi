<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

/**
 * Автомат нэвтрэлтийн browser өргөтгөлийг задгай файлаар (хавтас) татуулна.
 * ZIP биш — Chrome/Edge «Load unpacked»-д шууд өгөхөд бэлэн.
 */
class ExtensionController extends Controller
{
    private const FOLDER = 'manage-dornogovi-extension';

    /** @return list<string> */
    private function relativeFiles(): array
    {
        $dir = base_path('browser-extension');
        $files = [];

        foreach ([
            'manifest.json', 'bridge.js', 'autofill.js', 'background.js',
            'popup.html', 'popup.js', 'README.md',
        ] as $file) {
            if (is_file($dir.DIRECTORY_SEPARATOR.$file)) {
                $files[] = $file;
            }
        }

        foreach (glob($dir.DIRECTORY_SEPARATOR.'icons'.DIRECTORY_SEPARATOR.'*.png') ?: [] as $icon) {
            $files[] = 'icons/'.basename($icon);
        }

        sort($files);

        return $files;
    }

    public function download(): JsonResponse
    {
        $dir = base_path('browser-extension');

        abort_unless(is_dir($dir), 404);

        $payload = [];

        foreach ($this->relativeFiles() as $relative) {
            $path = $dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            abort_unless(is_file($path), 404);

            $raw = File::get($path);
            $isBinary = str_ends_with(strtolower($relative), '.png');

            $payload[$relative] = [
                'encoding' => $isBinary ? 'base64' : 'utf-8',
                'content' => $isBinary ? base64_encode($raw) : $raw,
            ];
        }

        return response()->json([
            'folder' => self::FOLDER,
            'files' => $payload,
        ]);
    }
}
