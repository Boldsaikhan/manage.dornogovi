<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\PhoneDirectoryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Баталсан» баганын сонголт — удирдлагууд ба хэлтсийн дарга нар.
 */
class LeaveSignerOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function entry(array $attributes): PhoneDirectoryEntry
    {
        return PhoneDirectoryEntry::create(array_merge([
            'org_name' => 'Удирдлагууд',
            'category' => 'udirdlaga',
            'org_order' => 1,
            'sort_order' => 1,
        ], $attributes));
    }

    public function test_leaders_and_department_heads_are_offered(): void
    {
        $this->entry(['person_name' => 'О.Батжаргал', 'position' => 'Засаг дарга']);
        $this->entry([
            'org_name' => 'Нийгмийн бодлогын хэлтэс',
            'category' => 'heltes',
            'org_order' => 2,
            'person_name' => 'Н.Мөнхцэцэг',
            'position' => 'Хэлтсийн дарга',
        ]);

        $options = Leave::signerOptions();

        $this->assertSame('О.Батжаргал — Засаг дарга', $options['О.Батжаргал']);
        $this->assertSame('Н.Мөнхцэцэг — Хэлтсийн дарга', $options['Н.Мөнхцэцэг']);
    }

    public function test_the_leadership_group_is_offered_whatever_the_title(): void
    {
        $this->entry(['person_name' => 'Б.Сэргэлэнбаяр', 'position' => 'Ахлах референт']);

        $this->assertArrayHasKey('Б.Сэргэлэнбаяр', Leave::signerOptions());
    }

    public function test_an_ordinary_officer_is_not_offered(): void
    {
        $this->entry([
            'org_name' => 'Нийгмийн бодлогын хэлтэс',
            'category' => 'heltes',
            'org_order' => 2,
            'person_name' => 'А.Бадрал',
            'position' => 'Мэргэжилтэн',
        ]);

        $this->assertArrayNotHasKey('А.Бадрал', Leave::signerOptions());
    }
}
