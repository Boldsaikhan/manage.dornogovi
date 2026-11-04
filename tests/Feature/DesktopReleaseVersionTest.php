<?php

namespace Tests\Feature;

use App\Http\Controllers\DesktopAppController;
use App\Support\DesktopRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ширээний програмын хувилбар суулгацтайгаа зөрөх ёсгүй.
 *
 * Зөрвөл програм шинэчлэлт татаад хуучин хувилбараа л дахин суулгаж,
 * төгсгөлгүй давтана.
 */
class DesktopReleaseVersionTest extends TestCase
{
    use RefreshDatabase;

    private string $versionFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versionFile = base_path('resources/desktop/version.json');
    }

    public function test_the_bundled_version_file_matches_the_desktop_project(): void
    {
        $this->assertFileExists(
            $this->versionFile,
            'resources/desktop/version.json алга — build хийхэд бичигдэх ёстой.',
        );

        $bundled = json_decode((string) file_get_contents($this->versionFile), true);
        $package = json_decode((string) file_get_contents(base_path('desktop-app/package.json')), true);

        $this->assertSame(
            $package['version'],
            $bundled['version'] ?? null,
            'version.json нь desktop-app/package.json-тэй таарахгүй байна.',
        );
    }

    public function test_the_manifest_uses_the_bundled_version(): void
    {
        // Админ буруу (хуучин) дугаар бичсэн ч суулгацынх нь хүчинтэй.
        DesktopRelease::save(['version' => '1.0.0', 'force' => false, 'notes' => '']);

        $uploaded = DesktopAppController::path();
        $moved = null;

        // Админы байршуулсан файл байвал туршилтын хугацаанд зөөнө.
        if (is_file($uploaded)) {
            $moved = $uploaded.'.test-backup';
            rename($uploaded, $moved);
        }

        try {
            clearstatcache();
            $this->assertTrue(DesktopAppController::servingBundled());
            $this->assertSame(
                DesktopAppController::bundledVersion(),
                DesktopRelease::manifest()['version'],
            );
        } finally {
            if ($moved !== null) {
                rename($moved, $uploaded);
            }
        }
    }

    public function test_an_uploaded_installer_keeps_the_version_the_admin_typed(): void
    {
        DesktopRelease::save(['version' => '9.9.9', 'force' => false, 'notes' => '']);

        $uploaded = DesktopAppController::path();
        $created = false;

        if (! is_file($uploaded)) {
            if (! is_dir(dirname($uploaded))) {
                mkdir(dirname($uploaded), 0775, true);
            }

            file_put_contents($uploaded, 'MZ'.str_repeat('0', 64));
            $created = true;
        }

        // Админ саяхан байршуулсан гэж үзнэ — хамгийн шинэ нь түгээгдэнэ.
        $was = filemtime($uploaded);
        touch($uploaded, time() + 60);
        clearstatcache();

        try {
            $this->assertFalse(DesktopAppController::servingBundled());
            $this->assertSame('9.9.9', DesktopRelease::manifest()['version']);
        } finally {
            if ($created) {
                unlink($uploaded);
            } else {
                touch($uploaded, $was);
            }

            clearstatcache();
        }
    }

    public function test_the_version_endpoint_is_open_without_logging_in(): void
    {
        $this->getJson('/desktop/version')
            ->assertOk()
            ->assertJsonStructure(['version', 'force', 'notes', 'url', 'size', 'available']);
    }
}
