<?php

namespace Tests\Feature;

use App\Models\LibraryFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
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

    public function test_user_without_access_gets_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('file-library.index'))
            ->assertForbidden();
    }

    public function test_view_access_can_list_and_download(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->grantModule($user, 'file_library', 'view');

        $path = 'file-library/sample.pdf';
        Storage::disk('local')->put($path, 'pdf-bytes');

        $file = LibraryFile::query()->create([
            'title' => 'Жишээ',
            'original_name' => 'sample.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => 9,
            'folder' => 'Загвар',
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('file-library.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/FileLibrary')
                ->where('canManage', false)
                ->has('files', 1)
                ->where('files.0.name', 'Жишээ')
            );

        $this->actingAs($user)
            ->get(route('file-library.download', $file))
            ->assertOk();
    }

    public function test_admin_can_upload_and_delete(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $upload = UploadedFile::fake()->create('guide.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->actingAs($admin)
            ->post(route('file-library.store'), [
                'title' => 'Гарын авлага',
                'folder' => 'Сургалт',
                'file' => $upload,
            ])
            ->assertRedirect();

        $row = LibraryFile::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('Гарын авлага', $row->title);
        $this->assertSame('Сургалт', $row->folder);
        $this->assertSame($admin->id, $row->uploaded_by);
        Storage::disk('local')->assertExists($row->path);

        $this->actingAs($admin)
            ->delete(route('file-library.destroy', $row))
            ->assertRedirect(route('file-library.index'));

        $this->assertDatabaseCount('library_files', 0);
        Storage::disk('local')->assertMissing($row->path);
    }

    public function test_rejects_disallowed_extension(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $upload = UploadedFile::fake()->create('malware.exe', 40, 'application/octet-stream');

        $this->actingAs($admin)
            ->from(route('file-library.index'))
            ->post(route('file-library.store'), [
                'file' => $upload,
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('library_files', 0);
    }

    public function test_edit_access_required_to_upload(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->grantModule($user, 'file_library', 'view');

        $upload = UploadedFile::fake()->create('note.pdf', 20, 'application/pdf');

        $this->actingAs($user)
            ->post(route('file-library.store'), [
                'file' => $upload,
            ])
            ->assertForbidden();
    }

    public function test_module_appears_in_nav_for_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('file-library.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/FileLibrary')
                ->where('canManage', true)
            );
    }

    public function test_can_update_title_and_folder(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $path = 'file-library/a.pdf';
        Storage::disk('local')->put($path, 'x');

        $file = LibraryFile::query()->create([
            'title' => 'Хуучин',
            'original_name' => 'a.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => 1,
            'folder' => null,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('file-library.update', $file), [
                'title' => 'Шинэ нэр',
                'folder' => 'Дотоод',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('library_files', [
            'id' => $file->id,
            'title' => 'Шинэ нэр',
            'folder' => 'Дотоод',
        ]);
    }
}
