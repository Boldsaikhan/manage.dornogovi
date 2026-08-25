<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PhoneDirectoryCategoryTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_heltes_category_is_available_and_kept(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $entry = PhoneDirectoryEntry::create([
            'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'category' => 'heltes',
            'person_name' => 'Ц.Сансармаа',
            'position' => 'Хэлтсийн дарга',
            'org_order' => 1,
            'sort_order' => 1,
        ]);

        // Хуудсанд «Хэлтэс» ангилал сонголт болон бүлгийн төлөв хэвээр ирнэ
        $this->actingAs($admin)
            ->get(route('phone-directory.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/PhoneDirectory')
                ->where('categories.heltes', 'Хэлтэс')
                ->where('groups.0.category', 'heltes'));

        // Хэлтэс болгож сонгоход хадгалагдана
        $this->actingAs($admin)
            ->patch(route('phone-directory.category'), [
                'org_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
                'category' => 'heltes',
            ])
            ->assertRedirect();

        $this->assertSame('heltes', $entry->fresh()->category);
    }
}
