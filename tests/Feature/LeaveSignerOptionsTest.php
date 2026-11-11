<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\PhoneDirectoryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Баталсан» баганын сонголт — зөвхөн аймгийн удирдлага ба АЗДТГ-ын
 * хэлтсийн дарга нар.
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

    private function unit(array $attributes): PhoneDirectoryEntry
    {
        return $this->entry(array_merge([
            'org_name' => 'Нийгмийн бодлогын хэлтэс',
            'category' => 'heltes',
            'org_order' => 2,
        ], $attributes));
    }

    public function test_the_aimag_leaders_are_offered(): void
    {
        $this->entry(['person_name' => 'О.Батжаргал', 'position' => 'Засаг дарга']);
        $this->entry(['person_name' => 'Г.Март', 'position' => 'Засаг даргын орлогч', 'sort_order' => 2]);
        $this->entry(['person_name' => 'М.Мөнхбат', 'position' => 'ЗДТГ-ын дарга', 'sort_order' => 3]);

        $options = Leave::signerOptions();

        $this->assertSame('О.Батжаргал — Засаг дарга', $options['О.Батжаргал']);
        $this->assertArrayHasKey('Г.Март', $options);
        $this->assertArrayHasKey('М.Мөнхбат', $options);
    }

    public function test_a_department_head_of_the_office_is_offered(): void
    {
        $this->unit(['person_name' => 'Н.Мөнхцэцэг', 'position' => 'Хэлтсийн дарга']);

        $this->assertArrayHasKey('Н.Мөнхцэцэг', Leave::signerOptions());
    }

    public function test_assistants_are_not_offered(): void
    {
        $this->entry(['person_name' => 'Н.Мэндсайхан', 'position' => 'Засаг даргын туслах']);
        $this->entry([
            'person_name' => 'Б.Сэргэлэнбаяр',
            'position' => 'Засаг даргын орлогчийн туслах',
            'sort_order' => 2,
        ]);

        $options = Leave::signerOptions();

        $this->assertArrayNotHasKey('Н.Мэндсайхан', $options);
        $this->assertArrayNotHasKey('Б.Сэргэлэнбаяр', $options);
    }

    public function test_soum_and_agency_leaders_are_not_offered(): void
    {
        $this->entry([
            'org_name' => 'Замын-Үүд сум',
            'category' => 'sum',
            'org_order' => 5,
            'person_name' => 'С.Бурмаа',
            'position' => 'Тамгын газрын дарга',
        ]);
        $this->entry([
            'org_name' => 'Онцгой байдлын газар',
            'category' => 'agentlag',
            'org_order' => 6,
            'person_name' => 'Н.Оюунболд',
            'position' => 'Замын-Үүд сум дахь Онцгой байдлын хэлтсийн дарга',
        ]);

        $options = Leave::signerOptions();

        $this->assertArrayNotHasKey('С.Бурмаа', $options);
        $this->assertArrayNotHasKey('Н.Оюунболд', $options);
    }

    public function test_an_ordinary_officer_is_not_offered(): void
    {
        $this->unit(['person_name' => 'А.Бадрал', 'position' => 'Мэргэжилтэн']);

        $this->assertArrayNotHasKey('А.Бадрал', Leave::signerOptions());
    }
}
