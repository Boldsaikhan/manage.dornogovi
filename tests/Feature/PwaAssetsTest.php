<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    public function test_manifest_icons_and_service_worker_exist(): void
    {
        foreach ([
            'manifest.webmanifest',
            'sw.js',
            'icons/icon-192.png',
            'icons/icon-512.png',
            'icons/maskable-512.png',
            'icons/apple-touch-icon.png',
        ] as $file) {
            $this->assertFileExists(public_path($file));
        }

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertCount(3, $manifest['icons']);
    }

    public function test_layout_links_manifest(): void
    {
        $blade = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString('rel="manifest"', $blade);
        $this->assertStringContainsString('apple-touch-icon', $blade);
        $this->assertStringContainsString('theme-color', $blade);
    }
}
