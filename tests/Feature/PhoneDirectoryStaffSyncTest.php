<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use App\Services\PhoneDirectoryStaffSyncer;
use App\Support\PhoneDirectoryStaffListParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneDirectoryStaffSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_reads_surname_given_mobile_email(): void
    {
        $people = app(PhoneDirectoryStaffListParser::class)->fromRows([
            ['Гарчиг'],
            ['Овог', 'Нэр', 'Гар утас', 'И-Мейл хаяг'],
            ['Цогтгэрэл', 'Сансармаа', '9111-6259', 'sansarmaa@dornogovi.gov.mn'],
        ]);

        $this->assertCount(1, $people);
        $this->assertSame('Цогтгэрэл', $people[0]['surname']);
        $this->assertSame('Сансармаа', $people[0]['given']);
        $this->assertSame('91116259', $people[0]['mobile']);
        $this->assertSame('sansarmaa@dornogovi.gov.mn', $people[0]['email']);
    }

    public function test_updates_directory_and_user_by_name_when_phone_changed(): void
    {
        $entry = PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'category' => 'heltes',
            'person_name' => 'А.Алтанчимэг',
            'position' => 'Оператор',
            'mobile_phone' => '91649190',
            'org_order' => 1,
            'sort_order' => 1,
        ]);

        $user = User::factory()->create([
            'name' => 'А. Алтанчимэг',
            'phone' => '91649190',
            'email' => 'old-altanchimeg@dornogovi.gov.mn',
            'is_specialist' => true,
        ]);

        $result = app(PhoneDirectoryStaffSyncer::class)->sync([
            [
                'surname' => 'Ариунжаргал',
                'given' => 'Алтанчимэг',
                'mobile' => '91449190',
                'email' => 'altanchimeg@dornogovi.gov.mn',
            ],
        ]);

        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['users']);

        $entry->refresh();
        $user->refresh();
        $this->assertSame('А.Алтанчимэг', $entry->person_name);
        $this->assertSame('91449190', $entry->mobile_phone);
        $this->assertSame('Оператор', $entry->position);
        $this->assertSame('А.Алтанчимэг', $user->name);
        $this->assertSame('91449190', $user->phone);
        $this->assertSame('altanchimeg@dornogovi.gov.mn', $user->email);
    }

    public function test_creates_missing_directory_row(): void
    {
        $result = app(PhoneDirectoryStaffSyncer::class)->sync([
            [
                'surname' => 'Батболд',
                'given' => 'Болдсайхан',
                'mobile' => '89239655',
                'email' => 'boldsaikhan@dornogovi.gov.mn',
            ],
        ]);

        $this->assertSame(1, $result['created']);
        $this->assertTrue(
            PhoneDirectoryEntry::query()->where('person_name', 'Б.Болдсайхан')->where('mobile_phone', '89239655')->exists()
        );
    }

    public function test_artisan_command_reads_json(): void
    {
        PhoneDirectoryEntry::create([
            'org_name' => 'Засаг даргын Тамгын газар',
            'person_name' => 'Ц.Сансармаа',
            'mobile_phone' => '00000000',
            'org_order' => 1,
            'sort_order' => 1,
        ]);

        $this->artisan('phone-directory:sync-staff', [
            'file' => database_path('data/phone-list-staff.json'),
        ])->assertSuccessful();

        $this->assertSame(
            '91116259',
            PhoneDirectoryEntry::query()->where('person_name', 'Ц.Сансармаа')->value('mobile_phone')
        );
    }
}
