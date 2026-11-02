<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use App\Support\XlsxTableReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_downloads_docx_xlsx_and_pdf_for_directive_tab(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = TaskSource::where('key', TaskSource::KEY_DIRECTIVE)->first();

        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Туршилтын үүрэг чиглэл',
            'period' => '08.01-09.30',
            'responsible' => 'Б.Дулмаа',
            'collaborator' => 'АЗДТГ-ын дарга',
            'note' => 'Явцтай',
            'progress' => 40,
            'sort_order' => 1,
        ]);

        foreach (['docx', 'xlsx', 'pdf'] as $format) {
            $response = $this->actingAs($admin)
                ->get(route('tasks.export', ['kind' => 'directive', 'format' => $format]));

            $response->assertOk();
            $this->assertGreaterThan(500, strlen($response->getContent()));
        }

        $docx = $this->actingAs($admin)
            ->get(route('tasks.export', ['kind' => 'directive', 'format' => 'docx']));
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            (string) $docx->headers->get('content-type'),
        );

        $xlsx = $this->actingAs($admin)
            ->get(route('tasks.export', ['kind' => 'directive', 'format' => 'xlsx']));
        $this->assertSame('PK', substr($xlsx->getContent(), 0, 2));

        $pdf = $this->actingAs($admin)
            ->get(route('tasks.export', ['kind' => 'directive', 'format' => 'pdf']));
        $this->assertSame('%PDF', substr($pdf->getContent(), 0, 4));
    }

    public function test_export_downloads_only_selected_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = TaskSource::where('key', TaskSource::KEY_DIRECTIVE)->first();

        $kept = Task::create([
            'task_source_id' => $source->id,
            'text' => 'Сонгосон үүрэг',
            'progress' => 80,
            'sort_order' => 1,
        ]);
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Үлдээх үүрэг',
            'progress' => 10,
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('tasks.export', [
                'kind' => 'directive',
                'format' => 'xlsx',
                'ids' => (string) $kept->id,
            ]));

        $response->assertOk();

        $path = storage_path('framework/testing/task-selected.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $response->getContent());

        $flat = collect(app(XlsxTableReader::class)->rows($path))->flatten()->implode(' ');
        $this->assertStringContainsString('Сонгосон үүрэг', $flat);
        $this->assertStringNotContainsString('Үлдээх үүрэг', $flat);

        @unlink($path);
    }

    public function test_export_rejects_unknown_format(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('tasks.export', ['kind' => 'directive', 'format' => 'csv']))
            ->assertNotFound();
    }
}
