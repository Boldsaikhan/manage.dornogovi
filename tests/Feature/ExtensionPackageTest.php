<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ExtensionPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_zip_contains_ready_folder_with_icons_and_popup(): void
    {
        $user = User::factory()->create();

        $body = $this->actingAs($user)->get(route('extension.download'))->assertOk()->getContent();

        $tmp = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($tmp, $body);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($tmp);

        foreach ([
            'manage-dornogovi-extension/manifest.json',
            'manage-dornogovi-extension/popup.html',
            'manage-dornogovi-extension/popup.js',
            'manage-dornogovi-extension/icons/icon-128.png',
        ] as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    public function test_manifest_has_name_icons_and_secure_channel(): void
    {
        $manifest = json_decode(file_get_contents(base_path('browser-extension/manifest.json')), true);

        $this->assertSame('Manage Dornogovi', $manifest['name']);
        $this->assertArrayHasKey('128', $manifest['icons']);
        $this->assertArrayHasKey('default_popup', $manifest['action']);
        $this->assertNotEmpty($manifest['key']);
        $this->assertContains('https://manage.dornogovi.gov.mn/*', $manifest['externally_connectable']['matches']);

        // Устгах товч popup дээр бий
        $popup = file_get_contents(base_path('browser-extension/popup.html'));
        $this->assertStringContainsString('id="uninstall"', $popup);

        $background = file_get_contents(base_path('browser-extension/background.js'));
        $this->assertStringContainsString('uninstallSelf', $background);
    }
}
