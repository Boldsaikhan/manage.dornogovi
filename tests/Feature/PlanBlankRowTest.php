<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Plan;
use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Төлөвлөгөөг Үүрэг даалгавар цэс шиг «Шинэ нэмэх» дарж хоосон мөр
 * нэмээд, нүд нүдээр нь бөглөдөг болгосон.
 */
class PlanBlankRowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_button_adds_a_blank_row_with_the_creators_department(): void
    {
        $department = Department::create(['name' => 'Санхүүгийн хэлтэс', 'code' => 'FIN', 'sort_order' => 1, 'is_active' => true]);
        $admin = User::factory()->create(['is_admin' => true, 'department_id' => $department->id]);

        $this->actingAs($admin)
            ->post(route('modules.store', ['module' => 'plans']), ['blank' => true])
            ->assertRedirect()
            ->assertSessionHas('success');

        $row = Plan::query()->sole();
        $this->assertNull($row->title);
        $this->assertSame($admin->id, $row->created_by);
        $this->assertSame($department->id, $row->department_id);
    }

    public function test_the_blank_rows_cells_can_be_filled_in_one_by_one(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('modules.store', ['module' => 'plans']), ['blank' => true]);
        $row = Plan::query()->sole();

        $this->actingAs($admin)
            ->post(route('modules.field', ['module' => 'plans', 'id' => $row->id]), [
                'field' => 'title',
                'value' => 'Хэлтсийн жилийн төлөвлөгөө',
            ])
            ->assertRedirect();

        $this->assertSame('Хэлтсийн жилийн төлөвлөгөө', $row->fresh()->title);
    }

    public function test_the_supervisor_department_cell_offers_only_heltes_category_names(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::create([
            'org_name' => 'Санхүүгийн хэлтэс',
            'category' => 'heltes',
            'person_name' => 'Б.Должин',
            'position' => 'Дарга',
        ]);
        PhoneDirectoryEntry::create([
            'org_name' => 'Эрдэнэ сум',
            'category' => 'sum',
            'person_name' => 'Г.Ганбүрэн',
            'position' => 'Засаг дарга',
        ]);

        $this->actingAs($admin)->post(route('modules.store', ['module' => 'plans']), ['blank' => true]);
        $row = Plan::query()->sole();

        $this->actingAs($admin)
            ->post(route('modules.field', ['module' => 'plans', 'id' => $row->id]), [
                'field' => 'supervisor_department',
                'value' => 'Санхүүгийн хэлтэс',
            ])
            ->assertRedirect();

        $this->assertSame('Санхүүгийн хэлтэс', $row->fresh()->supervisor_department);

        $this->actingAs($admin)
            ->post(route('modules.field', ['module' => 'plans', 'id' => $row->id]), [
                'field' => 'supervisor_department',
                'value' => 'Эрдэнэ сум',
            ])
            ->assertSessionHasErrors('value');
    }
}
