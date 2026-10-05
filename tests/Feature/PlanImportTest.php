<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Support\XlsxTableWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Төлөвлөгөөг Үүрэг даалгавар цэс шиг Excel/Word файлаас оруулна.
 */
class PlanImportTest extends TestCase
{
    use RefreshDatabase;

    private function excel(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'plan_').'.xlsx';

        app(XlsxTableWriter::class)->write(
            $path,
            'ТӨЛӨВЛӨГӨӨ',
            ['Гарчиг', 'Он', 'Хугацаа', 'Төлөв'],
            $rows,
        );

        return new UploadedFile($path, 'plans.xlsx', null, null, true);
    }

    public function test_the_preview_maps_the_columns(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $file = $this->excel([
            ['Хэлтсийн жилийн төлөвлөгөө', '2026', 'I улирал', 'Хэрэгжиж буй'],
            ['Байгууллагын төлөвлөгөө', '2026', 'Жилийн', 'Ноорог'],
        ]);

        $data = $this->actingAs($admin)
            ->post(route('modules.import.preview', ['module' => 'plans']), ['file' => $file])
            ->assertOk()
            ->json();

        $this->assertSame(2, $data['total']);
        $this->assertSame(0, $data['mapping']['title']);
        $this->assertSame(1, $data['mapping']['year']);

        $first = $data['entries'][0];
        $this->assertSame('Хэлтсийн жилийн төлөвлөгөө', $first['title']);
        $this->assertSame(2026, $first['year']);
        $this->assertSame('I улирал', $first['period']);
        $this->assertSame('active', $first['status']);

        // Урьдчилан харах нь юу ч хадгалахгүй.
        $this->assertSame(0, Plan::query()->count());
    }

    public function test_rows_are_stored_with_the_uploaders_department(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('modules.import.store', ['module' => 'plans']), [
                'entries' => [
                    ['title' => 'Хэлтсийн жилийн төлөвлөгөө', 'year' => 2026, 'period' => 'I улирал', 'status' => 'active'],
                    ['title' => 'Хоёр дахь төлөвлөгөө', 'year' => 2026, 'period' => null, 'status' => null],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, Plan::query()->count());

        $row = Plan::query()->where('title', 'Хэлтсийн жилийн төлөвлөгөө')->firstOrFail();
        $this->assertSame($admin->id, $row->created_by);
        $this->assertSame('active', $row->status);

        // Статус өгөгдөөгүй бол тохиргооны анхны утга (draft) орно.
        $fallback = Plan::query()->where('title', 'Хоёр дахь төлөвлөгөө')->firstOrFail();
        $this->assertSame('draft', $fallback->status);
    }

    public function test_duplicate_titles_are_skipped(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $payload = ['entries' => [['title' => 'Давхардсан гарчиг', 'year' => 2026]]];

        $this->actingAs($admin)->post(route('modules.import.store', ['module' => 'plans']), $payload);
        $this->actingAs($admin)->post(route('modules.import.store', ['module' => 'plans']), $payload);

        $this->assertSame(1, Plan::query()->count());
    }

    public function test_a_viewer_without_edit_access_cannot_import(): void
    {
        $viewer = User::factory()->create();
        \App\Models\UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => 'plans',
            'level' => 'view',
        ]);

        $this->actingAs($viewer)
            ->post(route('modules.import.store', ['module' => 'plans']), [
                'entries' => [['title' => 'Эрхгүй оролдлого']],
            ])
            ->assertForbidden();

        $this->assertSame(0, Plan::query()->count());
    }
}
