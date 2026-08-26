<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use ZipArchive;

/**
 * Автомат нэвтрэлтийн browser өргөтгөлийг ZIP болгож татуулна.
 */
class ExtensionController extends Controller
{
    public function download(Request $request): Response
    {
        $dir = base_path('browser-extension');

        abort_unless(is_dir($dir), 404);

        $tmp = tempnam(sys_get_temp_dir(), 'ext');
        $zip = new ZipArchive;

        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Өргөтгөлийн багц үүсгэж чадсангүй.');
        }

        // ZIP дотор бэлэн хавтас — задлангуутаа «Load unpacked»-д шууд өгнө.
        $folder = 'manage-dornogovi-extension';

        foreach ([
            'manifest.json', 'bridge.js', 'autofill.js', 'background.js',
            'popup.html', 'popup.js', 'README.md',
        ] as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;

            if (is_file($path)) {
                $zip->addFile($path, $folder.'/'.$file);
            }
        }

        foreach (glob($dir.DIRECTORY_SEPARATOR.'icons'.DIRECTORY_SEPARATOR.'*.png') ?: [] as $icon) {
            $zip->addFile($icon, $folder.'/icons/'.basename($icon));
        }

        $zip->close();

        $content = (string) file_get_contents($tmp);
        @unlink($tmp);

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="manage-dornogovi-extension.zip"',
            'Content-Length' => (string) strlen($content),
        ]);
    }
}
