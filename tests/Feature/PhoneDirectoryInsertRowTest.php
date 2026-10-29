<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Утасны жагсаалт — мөр хооронд шинэ албан хаагч оруулах.
 */
class PhoneDirectoryInsertRowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function seedRows(): array
    {
        $rows = [];

        foreach (['Нэгдүгээр', 'Хоёрдугаар', 'Гуравдугаар'] as $i => $name) {
            $rows[] = PhoneDirectoryEntry::create([
                'org_name' => 'Тест хэлтэс',
                'person_name' => $name,
                'org_order' => 1,
                'sort_order' => $i + 1,
            ]);
        }

        return $rows;
    }

    public function test_a_row_is_inserted_before_the_chosen_one(): void
    {
        [$first, $second, $third] = $this->seedRows();

        $this->actingAs($this->admin())
            ->post(route('phone-directory.store'), [
                'org_name' => 'Тест хэлтэс',
                'person_name' => 'Шинэ хүн',
                'before_id' => $second->id,
            ])
            ->assertRedirect();

        $order = PhoneDirectoryEntry::where('org_name', 'Тест хэлтэс')
            ->orderBy('sort_order')->orderBy('id')
            ->pluck('person_name')->all();

        $this->assertSame(
            ['Нэгдүгээр', 'Шинэ хүн', 'Хоёрдугаар', 'Гуравдугаар'],
            $order,
        );
    }

    public function test_without_before_id_the_row_goes_last(): void
    {
        $this->seedRows();

        $this->actingAs($this->admin())
            ->post(route('phone-directory.store'), [
                'org_name' => 'Тест хэлтэс',
                'person_name' => 'Сүүлчийн',
            ])
            ->assertRedirect();

        $last = PhoneDirectoryEntry::where('org_name', 'Тест хэлтэс')
            ->orderByDesc('sort_order')->first();

        $this->assertSame('Сүүлчийн', $last->person_name);
    }

    public function test_a_before_id_from_another_group_is_ignored(): void
    {
        $this->seedRows();

        $other = PhoneDirectoryEntry::create([
            'org_name' => 'Өөр хэлтэс',
            'person_name' => 'Бусдынх',
            'org_order' => 2,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->admin())
            ->post(route('phone-directory.store'), [
                'org_name' => 'Тест хэлтэс',
                'person_name' => 'Шинэ хүн',
                'before_id' => $other->id,
            ])
            ->assertRedirect();

        $last = PhoneDirectoryEntry::where('org_name', 'Тест хэлтэс')
            ->orderByDesc('sort_order')->first();

        $this->assertSame('Шинэ хүн', $last->person_name);
        $this->assertSame(1, (int) $other->fresh()->sort_order);
    }
}
