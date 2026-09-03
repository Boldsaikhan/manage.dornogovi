<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ReportRowEdit;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Support\ReportRows;
use App\Support\ReportsCatalog;
use App\Support\ReportsData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportRowEditTest extends TestCase
{
    use RefreshDatabase;

    /** Мөртэй эхний тайланг олно. */
    private function reportWithRows(): array
    {
        foreach (ReportsCatalog::navigationTree() as $section) {
            foreach ($section['children'] ?? [] as $child) {
                if (ReportsData::rows($child['key'])) {
                    return ReportsCatalog::find($child['key']);
                }
            }
        }

        $this->markTestSkipped('Мөртэй тайлан алга.');
    }

    public function test_a_cell_edit_overrides_the_imported_value(): void
    {
        $report = $this->reportWithRows();
        $admin = User::factory()->create(['is_admin' => true]);
        $column = $report['columns'][1]['key'];

        $this->actingAs($admin)
            ->patch(route('reports.rows.update', [$report['key'], 0]), [
                'column' => $column,
                'value' => 'Гараар засварласан утга',
            ])
            ->assertRedirect();

        $rows = ReportRows::merged($report['key']);

        $this->assertSame('Гараар засварласан утга', $rows[0][$column]);
        // Бусад мөр хэвээр.
        $this->assertNotSame('Гараар засварласан утга', $rows[1][$column] ?? null);
    }

    public function test_unknown_column_is_rejected(): void
    {
        $report = $this->reportWithRows();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('reports.rows.update', [$report['key'], 0]), [
                'column' => 'baihgui_bagana',
                'value' => 'x',
            ])
            ->assertSessionHasErrors('column');
    }

    public function test_view_only_user_cannot_edit(): void
    {
        $report = $this->reportWithRows();
        $user = User::factory()->create();

        UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'reports',
            'level' => 'view',
        ]);

        $this->actingAs($user)
            ->patch(route('reports.rows.update', [$report['key'], 0]), [
                'column' => $report['columns'][1]['key'],
                'value' => 'x',
            ])
            ->assertForbidden();
    }

    public function test_department_scoped_user_sees_only_their_rows(): void
    {
        $report = $this->reportWithRows();
        $mine = Department::create(['name' => 'Миний хэлтэс', 'code' => 'MINE', 'sort_order' => 1, 'is_active' => true]);
        $other = Department::create(['name' => 'Өөр хэлтэс', 'code' => 'OTHER', 'sort_order' => 2, 'is_active' => true]);

        // 0-р мөр — миний хэлтэс, 1-р мөр — өөр хэлтэс.
        ReportRowEdit::create([
            'report_key' => $report['key'],
            'row_index' => 0,
            'column_key' => ReportRowEdit::DEPARTMENT_COLUMN,
            'department_id' => $mine->id,
        ]);

        ReportRowEdit::create([
            'report_key' => $report['key'],
            'row_index' => 1,
            'column_key' => ReportRowEdit::DEPARTMENT_COLUMN,
            'department_id' => $other->id,
        ]);

        $user = User::factory()->create(['department_id' => $mine->id]);

        UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'reports',
            'level' => 'view_own',
        ]);

        $rows = ReportRows::visibleTo(ReportRows::merged($report['key']), $user);
        $indexes = array_column($rows, '_index');

        $this->assertContains(0, $indexes, 'Өөрийн хэлтсийн мөр харагдах ёстой.');
        $this->assertNotContains(1, $indexes, 'Өөр хэлтсийн мөр харагдах ёсгүй.');

        // Хэлтэс сонгоогүй мөр бүх хүнд харагдана.
        $this->assertContains(2, $indexes);
    }

    public function test_full_access_user_sees_every_row(): void
    {
        $report = $this->reportWithRows();
        $dept = Department::create(['name' => 'Хэлтэс', 'code' => 'D1', 'sort_order' => 1, 'is_active' => true]);

        ReportRowEdit::create([
            'report_key' => $report['key'],
            'row_index' => 0,
            'column_key' => ReportRowEdit::DEPARTMENT_COLUMN,
            'department_id' => $dept->id,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $all = ReportRows::merged($report['key']);

        $this->assertCount(count($all), ReportRows::visibleTo($all, $admin));
    }
}
