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
            'dan-autofill.js',
            'install.bat',
            'СУУЛГАХ.txt',
            'icons/icon-128.png',
        ] as $expected) {
            $this->assertArrayHasKey($expected, $files);
            $this->assertNotEmpty($files[$expected]['content'] ?? null);
        }

        $this->assertSame('base64', $files['icons/icon-128.png']['encoding']);
        $this->assertSame('utf-8', $files['manifest.json']['encoding']);
        $this->assertGreaterThanOrEqual(10, count($files));
    }

    public function test_zip_contains_extension_folder(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('extension.download.zip'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');

        $tmp = tempnam(sys_get_temp_dir(), 'ext');
        file_put_contents($tmp, $response->streamedContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertNotFalse($zip->locateName('manage-dornogovi-extension/manifest.json'));
        $this->assertNotFalse($zip->locateName('manage-dornogovi-extension/install.bat'));
        $this->assertNotFalse($zip->locateName('manage-dornogovi-extension/dan-autofill.js'));
        $zip->close();
        @unlink($tmp);
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
