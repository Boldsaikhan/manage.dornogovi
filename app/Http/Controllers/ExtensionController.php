<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Автомат нэвтрэлтийн browser өргөтгөл — бүхэл хавтас (JSON эсвэл ZIP).
 */
class ExtensionController extends Controller
{
    private const FOLDER = 'manage-dornogovi-extension';

    /**
     * browser-extension доторх бүх файлыг харьцангуй замаар жагсаана.
     *
     * @return list<string>
     */
    private function relativeFiles(): array
    {
        $dir = base_path('browser-extension');
        $files = [];

        if (! is_dir($dir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));

            if ($relative === '' || str_starts_with($relative, '.')) {
                continue;
            }

            $files[] = $relative;
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<string, array{encoding: string, content: string}>
     */
    private function filesPayload(): array
    {
        $dir = base_path('browser-extension');
        $payload = [];

        foreach ($this->relativeFiles() as $relative) {
            $path = $dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! is_file($path)) {
                continue;
            }

            $raw = File::get($path);
            $isBinary = (bool) preg_match('/\.(png|jpg|jpeg|gif|webp|ico)$/i', $relative);

            $payload[$relative] = [
                'encoding' => $isBinary ? 'base64' : 'utf-8',
                'content' => $isBinary ? base64_encode($raw) : $raw,
            ];
        }

        return $payload;
    }

    /** Chrome/Edge хавтас сонгож бичихэд ашиглана. */
    public function download(): JsonResponse
    {
        abort_unless(is_dir(base_path('browser-extension')), 404);

        $files = $this->filesPayload();
        abort_unless($files !== [], 404);

        return response()->json([
            'folder' => self::FOLDER,
            'files' => $files,
            'count' => count($files),
        ]);
    }

    /**
     * Нэг ZIP — дотор нь manage-dornogovi-extension/ хавтас (бүх файл).
     * Задаад Load unpacked хийнэ.
     */
    public function downloadZip(): StreamedResponse
    {
        abort_unless(is_dir(base_path('browser-extension')), 404);

        $files = $this->relativeFiles();
        abort_unless($files !== [], 404);

        $tmp = tempnam(sys_get_temp_dir(), 'extzip');
        $zipPath = $tmp.'.zip';
        @unlink($tmp);

        $zip = new ZipArchive;
        abort_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500);

        $dir = base_path('browser-extension');

        foreach ($files as $relative) {
            $path = $dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! is_file($path)) {
                continue;
            }
            $zip->addFile($path, self::FOLDER.'/'.$relative);
        }

        $zip->close();

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            @unlink($zipPath);
        }, self::FOLDER.'.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
