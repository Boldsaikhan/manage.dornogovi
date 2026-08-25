<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecreeExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_downloads_docx_xlsx_and_pdf_for_visible_tab(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Экспорт захирамж',
            'issued_on' => '2026-08-25',
            'person_name' => 'Б.Гантөмөр',
            'created_by' => $admin->id,
        ]);

        Decree::create([
            'category' => 'tushaal',
            'kind' => 'tushaal_a',
            'number' => '77',
            'title' => 'Өөр табын тушаал',
            'created_by' => $admin->id,
        ]);

        $docx = $this->actingAs($admin)
            ->get(route('decrees.export', ['tab' => 'zahiramj_a', 'format' => 'docx']));
        $docx->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            (string) $docx->headers->get('content-type'),
        );
        $this->assertGreaterThan(1000, strlen($docx->getContent()));

        $xlsx = $this->actingAs($admin)
            ->get(route('decrees.export', ['tab' => 'zahiramj_a', 'format' => 'xlsx']));
        $xlsx->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $xlsx->headers->get('content-type'),
        );
        // ZIP signature
        $this->assertSame('PK', substr($xlsx->getContent(), 0, 2));

        $pdf = $this->actingAs($admin)
            ->get(route('decrees.export', ['tab' => 'zahiramj_a', 'format' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertSame('%PDF', substr($pdf->getContent(), 0, 4));
    }

    public function test_export_rejects_unknown_format(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('decrees.export', ['tab' => 'blank', 'format' => 'csv']))
            ->assertNotFound();
    }
}
