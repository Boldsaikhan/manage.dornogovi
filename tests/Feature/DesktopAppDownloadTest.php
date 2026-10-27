<?php

namespace Tests\Feature;

use App\Http\Controllers\DesktopAppController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DesktopAppDownloadTest extends TestCase
{
    use RefreshDatabase;

    private ?string $backup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $path = DesktopAppController::path();

        // Жинхэнэ суулгац байвал тестийн турш хойш нь тавина.
        if (is_file($path)) {
            $this->backup = $path.'.test-backup';
            rename($path, $this->backup);
        }
    }

    protected function tearDown(): void
    {
        $path = DesktopAppController::path();
        @unlink($path);

        if ($this->backup !== null && is_file($this->backup)) {
            rename($this->backup, $path);
        }

        parent::tearDown();
    }

    private function writeInstaller(string $body = 'MZ-test'): void
    {
        $dir = dirname(DesktopAppController::path());

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(DesktopAppController::path(), $body);
    }

    public function test_guest_cannot_download_the_installer(): void
    {
        $this->get('/desktop/download')->assertRedirect('/login');
    }

    public function test_a_missing_installer_is_reported_instead_of_erroring(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/dashboard')
            ->get('/desktop/download')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('warning');
    }

    public function test_the_installer_is_served_when_present(): void
    {
        $this->writeInstaller();

        $this->actingAs(User::factory()->create())
            ->get('/desktop/download')
            ->assertOk()
            ->assertDownload('Manage-Dornogovi-Setup.exe');
    }

    public function test_only_an_admin_may_upload(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/desktop/upload', [
                'chunk' => UploadedFile::fake()->createWithContent('part', 'MZ'),
                'upload_id' => 'abcdef123456',
                'index' => 0,
                'total' => 1,
            ])
            ->assertForbidden();
    }

    public function test_an_admin_uploads_the_installer_in_chunks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $parts = ['MZ'.str_repeat('a', 100), str_repeat('b', 100), str_repeat('c', 100)];

        foreach ($parts as $index => $body) {
            $response = $this->actingAs($admin)->postJson('/desktop/upload', [
                'chunk' => UploadedFile::fake()->createWithContent('part', $body),
                'upload_id' => 'abcdef123456',
                'index' => $index,
                'total' => count($parts),
            ]);

            $response->assertOk();
            $response->assertJsonPath('done', $index === count($parts) - 1);
        }

        $this->assertSame(implode('', $parts), file_get_contents(DesktopAppController::path()));
    }

    public function test_a_file_that_is_not_a_windows_program_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->postJson('/desktop/upload', [
                'chunk' => UploadedFile::fake()->createWithContent('part', 'PK-zip'),
                'upload_id' => 'abcdef123456',
                'index' => 0,
                'total' => 1,
            ])
            ->assertStatus(422);

        $this->assertFileDoesNotExist(DesktopAppController::path());
    }
}
