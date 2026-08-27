<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * PWA manifest — одоогийн домэйн дээр суулгана (буруу related_applications-гүй).
 */
class PwaManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $startUrl = url('/dept-dashboard');

        return response()->json([
            'id' => $startUrl,
            'name' => 'manage дотоод систем',
            'short_name' => 'manage',
            'description' => 'Дорноговь аймгийн Засаг даргын Тамгын газрын дотоод удирдлагын систем',
            'lang' => 'mn',
            'dir' => 'ltr',
            'start_url' => $startUrl,
            'scope' => url('/'),
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#f1f5f9',
            'theme_color' => '#1c55a5',
            'icons' => [
                [
                    'src' => url('/icons/icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => url('/icons/icon-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => url('/icons/maskable-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                ['name' => 'Үүрэг даалгавар', 'url' => url('/uureg')],
                ['name' => 'Чөлөөний бүртгэл', 'url' => url('/modules/leaves')],
                ['name' => 'Утасны жагсаалт', 'url' => url('/phone-directory')],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
