<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_extension_package_downloads_as_json_files(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('extension.download'));

        $response->assertOk();
        $response->assertJsonStructure([
            'folder',
            'files' => [
                'manifest.json' => ['encoding', 'content'],
            ],
        ]);
        $response->assertHeader('content-type', 'application/json');
        $this->assertSame('manage-dornogovi-extension', $response->json('folder'));
    }

    public function test_guests_cannot_download(): void
    {
        $this->get(route('extension.download'))->assertRedirect(route('login'));
    }
}
