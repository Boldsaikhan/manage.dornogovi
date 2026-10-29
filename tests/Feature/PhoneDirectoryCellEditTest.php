<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Утасны жагсаалт — мөр бүрийг нүдээр нь тусад нь засах.
 */
class PhoneDirectoryCellEditTest extends TestCase
{
    use RefreshDatabase;

    private function entry(): PhoneDirectoryEntry
    {
        return PhoneDirectoryEntry::create([
            'org_name' => 'Тест хэлтэс',
            'person_name' => 'Н.Алдарбаяр',
            'position' => 'ИТХ-ын дарга',
            'office_phone' => '99023484',
            'org_order' => 1,
            'sort_order' => 1,
        ]);
    }

    public function test_each_field_can_be_saved_on_its_own(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = $this->entry();

        foreach ([
            'person_name' => 'Н.Алдарбаяр-Эрдэнэ',
            'position' => 'Хэлтсийн дарга',
            'office_phone' => '70520000',
            'mobile_phone' => '99112233',
        ] as $field => $value) {
            $this->actingAs($admin)
                ->patch(route('phone-directory.field', $row), ['field' => $field, 'value' => $value])
                ->assertRedirect();
        }

        $fresh = $row->fresh();

        $this->assertSame('Н.Алдарбаяр-Эрдэнэ', $fresh->person_name);
        $this->assertSame('Хэлтсийн дарга', $fresh->position);
        $this->assertSame('70520000', $fresh->office_phone);
        $this->assertSame('99112233', $fresh->mobile_phone);

        // Бусад талбар хөндөгдөөгүй.
        $this->assertSame('Тест хэлтэс', $fresh->org_name);
        $this->assertSame(1, (int) $fresh->sort_order);
    }

    public function test_an_empty_value_clears_the_field(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = $this->entry();

        $this->actingAs($admin)
            ->patch(route('phone-directory.field', $row), ['field' => 'office_phone', 'value' => ''])
            ->assertRedirect();

        $this->assertNull($row->fresh()->office_phone);
    }

    public function test_the_name_cannot_be_emptied(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = $this->entry();

        $this->actingAs($admin)
            ->from(route('phone-directory.index'))
            ->patch(route('phone-directory.field', $row), ['field' => 'person_name', 'value' => ''])
            ->assertRedirect();

        $this->assertSame('Н.Алдарбаяр', $row->fresh()->person_name);
    }

    public function test_an_unknown_field_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $row = $this->entry();

        $this->actingAs($admin)
            ->patch(route('phone-directory.field', $row), ['field' => 'org_name', 'value' => 'Өөр хэлтэс'])
            ->assertSessionHasErrors('field');

        $this->assertSame('Тест хэлтэс', $row->fresh()->org_name);
    }

    public function test_a_user_without_edit_rights_is_blocked(): void
    {
        $user = User::factory()->create();
        $row = $this->entry();

        $this->actingAs($user)
            ->patch(route('phone-directory.field', $row), ['field' => 'position', 'value' => 'Хакер'])
            ->assertForbidden();
    }
}
