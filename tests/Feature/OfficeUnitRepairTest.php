<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\PhoneDirectoryEntry;
use App\Support\OfficeUnits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тамгын газрын нэгж «байгууллага» гэж бүртгэгдсэнийг засах.
 */
class OfficeUnitRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_units_are_marked_and_existing_rows_are_moved(): void
    {
        PhoneDirectoryEntry::create([
            'org_name' => 'Хэвлэл мэдээллийн алба',
            'category' => 'baiguullaga',
            'person_name' => 'Б.Сайнжаргал',
            'position' => 'Мэргэжилтэн',
        ]);

        // Нэгжийн нэрээр бүртгэгдсэн хуучин чөлөө.
        $leave = Leave::create([
            'user_id' => \App\Models\User::factory()->create()->id,
            'scope' => 'baiguullaga',
            'org_name' => 'Хэвлэл мэдээллийн алба',
            'person_name' => 'Б.Сайнжаргал',
            'type' => 'tsalintai',
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-17',
            'days' => 1,
            'status' => 'approved',
        ]);

        OfficeUnits::markAndRepair();

        $this->assertSame('heltes', PhoneDirectoryEntry::query()->sole()->category);

        $fresh = $leave->fresh();
        $this->assertSame(PhoneDirectoryEntry::PARENT_ORG, $fresh->org_name);
        $this->assertSame('Хэвлэл мэдээллийн алба', $fresh->unit_name);
    }

    public function test_an_outside_organisation_is_left_alone(): void
    {
        PhoneDirectoryEntry::create([
            'org_name' => 'Эрүүл мэндийн газар',
            'category' => 'agentlag',
            'person_name' => 'Б.Болд',
            'position' => 'Дарга',
        ]);

        $leave = Leave::create([
            'user_id' => \App\Models\User::factory()->create()->id,
            'scope' => 'agentlag',
            'org_name' => 'Эрүүл мэндийн газар',
            'person_name' => 'Б.Болд',
            'type' => 'tsalintai',
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-17',
            'days' => 1,
            'status' => 'approved',
        ]);

        OfficeUnits::markAndRepair();

        $fresh = $leave->fresh();
        $this->assertSame('Эрүүл мэндийн газар', $fresh->org_name);
        $this->assertNull($fresh->unit_name);
    }
}
