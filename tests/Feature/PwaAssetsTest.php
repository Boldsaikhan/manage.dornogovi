<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    public function test_manifest_is_served_from_current_app_url(): void
    {
        foreach ([
            'sw.js',
            'icons/icon-192.png',
            'icons/icon-512.png',
            'icons/maskable-512.png',
            'icons/apple-touch-icon.png',
        ] as $file) {
            $this->assertFileExists(public_path($file));
        }

        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8');

        $manifest = $response->json();

        $this->assertSame('manage дотоод систем', $manifest['name']);
        $this->assertSame('manage', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertStringContainsString('/dept-dashboard', $manifest['start_url']);
        $this->assertCount(3, $manifest['icons']);
        $this->assertArrayNotHasKey('related_applications', $manifest);
    }

    public function test_layout_links_manifest(): void
    {
        $blade = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString('rel="manifest"', $blade);
        $this->assertStringContainsString('apple-touch-icon', $blade);
        $this->assertStringContainsString('theme-color', $blade);
    }
}
