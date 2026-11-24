<?php

namespace Tests\Feature;

use App\Models\LibraryFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_guest_cannot_open_file_library(): void
    {
        $this->get(route('file-library.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_open_file_library(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('file-library.index'))
            ->assertForbidden();
    }

    public function test_admin_is_redirected_to_settings_files_tab(): void
    {
        $this->actingAs($this->admin())
            ->get(route('file-library.index'))
            ->assertRedirect(route('admin.systems.index', ['tab' => 'files']));
    }

    public function test_admin_can_upload_and_delete(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $upload = UploadedFile::fake()->create(
            'guide.docx',
            120,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $this->actingAs($admin)
            ->post(route('file-library.store'), [
                'title' => 'Гарын авлага',
                'folder' => 'Сургалт',
                'file' => $upload,
            ])
            ->assertRedirect(route('admin.systems.index', ['tab' => 'files']));

        $row = LibraryFile::query()->firstOrFail();
        $this->assertSame('Гарын авлага', $row->title);
        $this->assertTrue(Storage::disk('local')->exists($row->path));

        $this->actingAs($admin)
            ->delete(route('file-library.destroy', $row))
            ->assertRedirect(route('admin.systems.index', ['tab' => 'files']));

        $this->assertDatabaseCount('library_files', 0);
    }

    public function test_admin_can_download_library_file(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $path = 'file-library/sample.pdf';
        Storage::disk('local')->put($path, 'pdf-bytes');

        $file = LibraryFile::query()->create([
            'title' => 'Жишээ',
            'original_name' => 'sample.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => 9,
            'folder' => 'Загвар',
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('file-library.download', $file))
            ->assertOk();
    }
}
