<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageBrowserTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_guest_cannot_browse_storage(): void
    {
        $this->getJson(route('admin.storage.browse', ['root' => 'private']))
            ->assertUnauthorized();
    }

    public function test_non_admin_cannot_browse_storage(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->getJson(route('admin.storage.browse', ['root' => 'private']))
            ->assertForbidden();
    }

    public function test_admin_can_browse_folders_and_preview_images(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('uploads');
        Storage::disk('local')->put('uploads/photo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
        Storage::disk('local')->put('uploads/note.txt', 'hello');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson(route('admin.storage.browse', ['root' => 'private']))
            ->assertOk()
            ->assertJsonPath('root', 'private')
            ->assertJsonFragment(['name' => 'uploads']);

        $this->actingAs($admin)
            ->getJson(route('admin.storage.browse', ['root' => 'private', 'path' => 'uploads']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'photo.png'])
            ->assertJsonFragment(['name' => 'note.txt']);

        $this->actingAs($admin)
            ->get(route('admin.storage.preview', ['root' => 'private', 'path' => 'uploads/photo.png']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.storage.download', ['root' => 'private', 'path' => 'uploads/note.txt']))
            ->assertOk();
    }

    public function test_path_traversal_is_blocked(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('safe.txt', 'ok');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson(route('admin.storage.browse', [
                'root' => 'private',
                'path' => '../',
            ]))
            ->assertNotFound();
    }

    public function test_old_file_library_url_redirects_admins_to_settings_files_tab(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('file-library.index'))
            ->assertRedirect(route('admin.systems.index', ['tab' => 'files']));
    }

    public function test_file_library_is_hidden_from_module_nav(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.systems.index'))
            ->assertOk();

        $nav = collect(config('modules.items'))->pluck('key');

        $this->assertFalse($nav->contains('file_library'));
    }
}
