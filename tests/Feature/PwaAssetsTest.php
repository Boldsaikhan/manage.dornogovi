<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    public function test_icon_assets_exist_for_browser_favicon(): void
    {
        foreach ([
            'icons/icon-192.png',
            'icons/icon-512.png',
            'icons/maskable-512.png',
            'icons/apple-touch-icon.png',
        ] as $file) {
            $this->assertFileExists(public_path($file));
        }
    }

    public function test_layout_does_not_advertise_pwa_install(): void
    {
        $blade = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringNotContainsString('rel="manifest"', $blade);
        $this->assertStringNotContainsString('apple-mobile-web-app-capable', $blade);
        $this->assertStringNotContainsString('mobile-web-app-capable', $blade);
        $this->assertStringContainsString('theme-color', $blade);
        $this->assertStringContainsString('/icons/icon-192.png', $blade);
    }

    public function test_app_js_unregisters_service_worker(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('unregister', $js);
        $this->assertStringNotContainsString("register('/sw.js')", $js);
    }
}
