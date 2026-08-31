<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    public function test_icon_assets_exist_for_pwa(): void
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

    public function test_layout_advertises_pwa_install_on_mobile(): void
    {
        $blade = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString('rel="manifest"', $blade);
        $this->assertStringContainsString('apple-mobile-web-app-capable', $blade);
        $this->assertStringContainsString('mobile-web-app-capable', $blade);
        $this->assertStringContainsString('theme-color', $blade);
        $this->assertStringContainsString('/icons/apple-touch-icon.png', $blade);
    }

    public function test_app_js_registers_service_worker_on_mobile(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $pwa = file_get_contents(resource_path('js/utils/pwaClient.js'));

        $this->assertStringContainsString('registerServiceWorker', $js);
        $this->assertStringContainsString("register('/sw.js'", $pwa);
        $this->assertStringNotContainsString('unregister', $js);
    }

    public function test_manifest_start_url_is_login(): void
    {
        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertJsonPath('start_url', url('/login'));
    }
}
