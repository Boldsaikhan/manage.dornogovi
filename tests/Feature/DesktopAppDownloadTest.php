<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopAppDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_download_the_installer(): void
    {
        $this->get('/desktop/download')->assertRedirect('/login');
    }

    public function test_a_missing_installer_is_reported_instead_of_erroring(): void
    {
        $path = storage_path('app/desktop/Manage-Dornogovi-Setup.exe');

        if (is_file($path)) {
            $this->markTestSkipped('Суулгац байршуулсан байна.');
        }

        $this->actingAs(User::factory()->create())
            ->from('/dashboard')
            ->get('/desktop/download')
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('desktop');
    }

    public function test_the_installer_is_served_when_present(): void
    {
        $dir = storage_path('app/desktop');
        $path = $dir.'/Manage-Dornogovi-Setup.exe';
        $existed = is_file($path);

        if (! $existed) {
            @mkdir($dir, 0775, true);
            file_put_contents($path, 'MZ-test');
        }

        try {
            $this->actingAs(User::factory()->create())
                ->get('/desktop/download')
                ->assertOk()
                ->assertDownload('Manage-Dornogovi-Setup.exe');
        } finally {
            if (! $existed) {
                @unlink($path);
            }
        }
    }
}
