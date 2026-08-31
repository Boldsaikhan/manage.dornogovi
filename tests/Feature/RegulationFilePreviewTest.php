<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RegulationFilePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_upload_is_shared_for_inline_preview(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $file = UploadedFile::fake()->create('журам.pdf', 80, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('modules.store', 'regulations'), [
                'title' => 'Кибер аюулгүй байдлын журам',
                'category' => 'cyber_security',
                'file' => $file,
            ])
            ->assertRedirect();

        $row = Regulation::query()->firstOrFail();
        $this->assertSame('cyber_security', $row->category);
        $this->assertSame('журам.pdf', $row->file_name);
        $this->assertNotNull($row->file_path);
        Storage::disk('local')->assertExists($row->file_path);

        $this->actingAs($admin)
            ->get(route('regulations.index', ['scope' => 'cyber_security']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/ResourceIndex')
                ->where('activeScope', 'cyber_security')
                ->where('rows.0.file_is_pdf', true)
                ->where('rows.0.file_name', 'журам.pdf')
                ->where('rows.0.file_url', route('modules.file', ['module' => 'regulations', 'id' => $row->id])));
    }

    public function test_uploaded_pdf_is_served_inline(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $file = UploadedFile::fake()->create('дотоод.pdf', 40, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('modules.store', 'regulations'), [
                'title' => 'Дотоод журам',
                'category' => 'internal',
                'file' => $file,
            ])
            ->assertRedirect();

        $row = Regulation::query()->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('modules.file', ['module' => 'regulations', 'id' => $row->id]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
    }
}
