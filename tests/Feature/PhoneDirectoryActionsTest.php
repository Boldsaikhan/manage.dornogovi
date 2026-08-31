<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneDirectoryActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_change_and_delete_work(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $entry = PhoneDirectoryEntry::create([
            'org_name' => 'Удирдлагууд',
            'category' => 'baiguullaga',
            'person_name' => 'О.Батжаргал',
            'position' => 'Засаг дарга',
            'org_order' => 1,
            'sort_order' => 1,
        ]);

        // Ангилал солих — «Аймгийн удирдлагууд»
        $this->actingAs($admin)
            ->patch(route('phone-directory.category'), [
                'org_name' => 'Удирдлагууд',
                'category' => 'udirdlaga',
            ])
            ->assertRedirect();

        $this->assertSame('udirdlaga', $entry->fresh()->category);

        // Сонголтгүй болгох
        $this->actingAs($admin)
            ->patch(route('phone-directory.category'), ['org_name' => 'Удирдлагууд', 'category' => ''])
            ->assertRedirect();

        $this->assertNull($entry->fresh()->category);

        // Устгах
        $this->actingAs($admin)
            ->delete(route('phone-directory.destroy', $entry))
            ->assertRedirect();

        $this->assertSame(0, PhoneDirectoryEntry::query()->count());
    }

    public function test_people_options_include_full_name_for_picker_search(): void
    {
        PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'category' => 'heltes',
            'person_name' => 'Батбаярын Дулмаа',
            'position' => 'Мэргэжилтэн',
            'org_order' => 1,
            'sort_order' => 1,
        ]);

        $people = PhoneDirectoryEntry::peopleOptions();

        $this->assertCount(1, $people);
        $this->assertSame('Б.Дулмаа', $people[0]['label']);
        $this->assertSame('Батбаярын Дулмаа', $people[0]['full']);
    }

    public function test_people_options_keeps_all_rows_when_short_names_collide(): void
    {
        PhoneDirectoryEntry::create([
            'org_name' => 'Улаанбадрах сум',
            'category' => 'sum',
            'person_name' => 'Батбаярын Дөлгөөн',
            'position' => 'Цэцэрлэгийн эрхлэгч',
            'org_order' => 1,
            'sort_order' => 1,
        ]);
        PhoneDirectoryEntry::create([
            'org_name' => 'Сайншанд сум',
            'category' => 'sum',
            'person_name' => 'Болдын Дөлгөөн',
            'position' => 'Сургуулийн захирал',
            'org_order' => 2,
            'sort_order' => 1,
        ]);
        PhoneDirectoryEntry::create([
            'org_name' => 'Дорноговь аймаг',
            'category' => 'baiguullaga',
            'person_name' => 'Дөлгөөнгийн Бат',
            'position' => 'Мэргэжилтэн',
            'org_order' => 3,
            'sort_order' => 1,
        ]);

        $people = PhoneDirectoryEntry::peopleOptions();

        $this->assertCount(3, $people);
        $this->assertSame(
            ['Батбаярын Дөлгөөн', 'Болдын Дөлгөөн', 'Д.Бат'],
            collect($people)->pluck('value')->all(),
        );
        $this->assertSame(
            ['Б.Дөлгөөн', 'Б.Дөлгөөн', 'Д.Бат'],
            collect($people)->pluck('label')->all(),
        );
    }
}
