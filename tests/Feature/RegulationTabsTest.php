<?php

namespace Tests\Feature;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RegulationTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_seeded_tabs_and_defaults_to_internal(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('regulations.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/ResourceIndex')
                ->where('activeScope', 'internal')
                ->where('scopeTabs.0.value', 'internal')
                ->where('scopeTabs.0.label', 'Дотоод журам')
                ->where('scopeTabs.1.value', 'labor_law')
                ->where('scopeTabs.1.label', 'Хөдөлмөрийн тухай хууль')
                ->where('scopeTabs.2.value', 'cyber_security')
                ->where('scopeTabs.2.label', 'Кибер аюулгүй байдлын дотоод журам')
            );
    }

    public function test_tab_lists_only_that_category(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Regulation::create([
            'title' => 'Дотоод',
            'category' => 'internal',
            'created_by' => $admin->id,
        ]);
        Regulation::create([
            'title' => 'Хөдөлмөр',
            'category' => 'labor_law',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('regulations.index', ['scope' => 'labor_law']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activeScope', 'labor_law')
                ->has('rows', 1)
                ->where('rows.0.title', 'Хөдөлмөр')
            );
    }

    public function test_upload_word_or_pdf_stores_on_active_tab(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $file = UploadedFile::fake()->create('кибер-журам.pdf', 80, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('modules.store', 'regulations'), [
                'title' => 'Кибер аюулгүй байдал',
                'category' => 'cyber_security',
                'file' => $file,
            ])
            ->assertRedirect();

        $row = Regulation::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('cyber_security', $row->category);
        $this->assertSame('Кибер аюулгүй байдал', $row->title);
        $this->assertSame('кибер-журам.pdf', $row->file_name);
        $this->assertNotEmpty($row->file_path);
        Storage::disk('local')->assertExists($row->file_path);

        $this->actingAs($admin)
            ->get(route('modules.file', ['module' => 'regulations', 'id' => $row->id]))
            ->assertOk();
    }

    public function test_rejects_non_word_pdf_upload(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('modules.store', 'regulations'), [
                'title' => 'Буруу файл',
                'category' => 'internal',
                'file' => UploadedFile::fake()->create('note.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_destroy_deletes_stored_file(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $path = UploadedFile::fake()->create('a.docx', 20)->store('regulations', 'local');

        $row = Regulation::create([
            'title' => 'Устгах',
            'category' => 'internal',
            'file_path' => $path,
            'file_name' => 'a.docx',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('modules.destroy', ['module' => 'regulations', 'id' => $row->id]))
            ->assertRedirect();

        $this->assertSame(0, Regulation::query()->count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_admin_can_add_update_and_delete_empty_tab(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('regulations.categories.store'), ['label' => 'Мэдээллийн аюулгүй байдал'])
            ->assertRedirect();

        $category = RegulationCategory::query()->where('label', 'Мэдээллийн аюулгүй байдал')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('regulations.categories.update', $category), [
                'label' => 'Мэдээллийн аюулгүй байдлын журам',
                'sort_order' => 9,
            ])
            ->assertRedirect();

        $category->refresh();
        $this->assertSame('Мэдээллийн аюулгүй байдлын журам', $category->label);
        $this->assertSame(9, $category->sort_order);

        $this->actingAs($admin)
            ->get(route('regulations.index', ['scope' => $category->key]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canManageScopes', true)
                ->has('scopeCategories', 4));

        $this->actingAs($admin)
            ->delete(route('regulations.categories.destroy', $category))
            ->assertRedirect(route('regulations.index', ['scope' => 'internal']));

        $this->assertDatabaseMissing('regulation_categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_tab_with_documents(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = RegulationCategory::query()->where('key', 'internal')->firstOrFail();

        Regulation::create([
            'title' => 'Журам',
            'category' => $category->key,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('regulations.categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('regulation_categories', ['id' => $category->id]);
    }

    public function test_specialist_cannot_manage_tabs(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_specialist' => true,
        ]);

        $this->actingAs($user)
            ->post(route('regulations.categories.store'), ['label' => 'Шинэ таб'])
            ->assertForbidden();
    }
}
