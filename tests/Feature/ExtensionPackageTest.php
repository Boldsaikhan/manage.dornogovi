<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_contains_loose_files_with_icons_and_popup(): void
    {
        $user = User::factory()->create();

        $json = $this->actingAs($user)
            ->getJson(route('extension.download'))
            ->assertOk()
            ->assertJsonPath('folder', 'manage-dornogovi-extension')
            ->json();

        $files = $json['files'] ?? [];

        foreach ([
            'manifest.json',
            'popup.html',
            'popup.js',
            'icons/icon-128.png',
        ] as $expected) {
            $this->assertArrayHasKey($expected, $files);
            $this->assertNotEmpty($files[$expected]['content'] ?? null);
        }

        $this->assertSame('base64', $files['icons/icon-128.png']['encoding']);
        $this->assertSame('utf-8', $files['manifest.json']['encoding']);
    }

    public function test_manifest_has_name_icons_and_secure_channel(): void
    {
        $manifest = json_decode(file_get_contents(base_path('browser-extension/manifest.json')), true);

        $this->assertSame('Manage Dornogovi', $manifest['name']);
        $this->assertArrayHasKey('128', $manifest['icons']);
        $this->assertArrayHasKey('default_popup', $manifest['action']);
        $this->assertNotEmpty($manifest['key']);
        $this->assertContains('https://manage.dornogovi.gov.mn/*', $manifest['externally_connectable']['matches']);

        $popup = file_get_contents(base_path('browser-extension/popup.html'));
        $this->assertStringContainsString('id="uninstall"', $popup);

        $background = file_get_contents(base_path('browser-extension/background.js'));
        $this->assertStringContainsString('uninstallSelf', $background);
    }
}
