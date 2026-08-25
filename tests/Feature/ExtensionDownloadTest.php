<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_extension_zip_downloads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('extension.download'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');

        $body = $response->getContent();
        $this->assertNotEmpty($body);
        $this->assertStringStartsWith('PK', $body);
    }

    public function test_guests_cannot_download(): void
    {
        $this->get(route('extension.download'))->assertRedirect(route('login'));
    }
}
