<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Утасны жагсаалтын бүлгийн гарчиг засах.
 */
class PhoneDirectoryGroupRenameTest extends TestCase
{
    use RefreshDatabase;

    private function entry(string $org, string $name): PhoneDirectoryEntry
    {
        return PhoneDirectoryEntry::create([
            'org_name' => $org,
            'person_name' => $name,
            'position' => 'Мэргэжилтэн',
        ]);
    }

    public function test_the_whole_group_is_renamed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->entry('Төрийн албаны салбар зөвлөл', 'Н.Баттуяа');
        $this->entry('Төрийн албаны салбар зөвлөл', 'Л.Оюунсүрэн');
        $this->entry('Өөр хэлтэс', 'Б.Болд');

        $this->actingAs($admin)
            ->from(route('phone-directory.index'))
            ->patch(route('phone-directory.group'), [
                'org_name' => 'Төрийн албаны салбар зөвлөл',
                'new_name' => 'Дорноговь аймаг дахь Төрийн албаны салбар зөвлөл',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            2,
            PhoneDirectoryEntry::query()
                ->where('org_name', 'Дорноговь аймаг дахь Төрийн албаны салбар зөвлөл')
                ->count(),
        );

        // Бусад бүлэг хөндөгдөхгүй.
        $this->assertSame(1, PhoneDirectoryEntry::query()->where('org_name', 'Өөр хэлтэс')->count());
    }

    public function test_renaming_onto_an_existing_group_merges_them(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->entry('Хуучин нэр', 'Н.Баттуяа');
        $this->entry('Шинэ нэр', 'Б.Болд');

        $this->actingAs($admin)
            ->from(route('phone-directory.index'))
            ->patch(route('phone-directory.group'), [
                'org_name' => 'Хуучин нэр',
                'new_name' => 'Шинэ нэр',
            ])
            ->assertRedirect();

        // Хоёр бүлэг нийлнэ — хэрэглэгчид мэдэгдэнэ.
        $this->assertSame(2, PhoneDirectoryEntry::query()->where('org_name', 'Шинэ нэр')->count());
    }

    public function test_a_viewer_cannot_rename_a_group(): void
    {
        $viewer = User::factory()->create(['is_admin' => false]);

        UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => 'phone_directory',
            'level' => 'view',
        ]);

        $this->entry('Хуучин нэр', 'Н.Баттуяа');

        $this->actingAs($viewer->fresh())
            ->patch(route('phone-directory.group'), [
                'org_name' => 'Хуучин нэр',
                'new_name' => 'Оролдлого',
            ])
            ->assertForbidden();

        $this->assertSame(1, PhoneDirectoryEntry::query()->where('org_name', 'Хуучин нэр')->count());
    }
}
