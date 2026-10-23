<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Windows суулгац — өргөтгөл + суулгах скрипт нэг ZIP-д.
 */
class DesktopSetupDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_setup_zip_holds_the_scripts_and_the_extension(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('extension.setup'));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'setup').'.zip';
        file_put_contents($path, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $zip->close();
        @unlink($path);

        // Суулгах скриптүүд.
        $this->assertContains('Manage-Dornogovi-Setup/install.ps1', $names);
        $this->assertContains('Manage-Dornogovi-Setup/uninstall.ps1', $names);
        $this->assertContains('Manage-Dornogovi-Setup/СУУЛГАХ.bat', $names);

        // Өргөтгөл нь «extension» дэд хавтаст — install.ps1 тэндээс хайна.
        $this->assertContains('Manage-Dornogovi-Setup/extension/manifest.json', $names);
    }

    public function test_a_guest_cannot_download_it(): void
    {
        $this->get(route('extension.setup'))->assertRedirect(route('login'));
    }
}
