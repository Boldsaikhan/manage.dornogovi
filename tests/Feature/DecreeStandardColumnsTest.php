<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DecreeStandardColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_number_tab_stores_printed_form_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'blank',
            'person_name' => 'Б.Батбаяр',
            'issued_on' => '2026-08-25',
            'qty_zahiramj' => 5,
            'qty_zahiramj_mn' => 2,
            'num_zahiramj' => '810-814',
            'void_zahiramj' => '811',
        ])->assertRedirect(route('decrees.index', ['tab' => 'blank']));

        $decree = Decree::query()->firstOrFail();
        $this->assertSame('blank', $decree->category);
        $this->assertSame(5, $decree->qty_zahiramj);
        $this->assertSame('810-814', $decree->num_zahiramj);

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'blank']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Decrees')
                ->where('tab', 'blank')
                ->where('rows.0.person_name', 'Б.Батбаяр')
                ->where('rows.0.num_zahiramj', '810-814'));
    }

    public function test_blank_row_can_be_created_empty_and_patched_inline(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'blank',
        ])->assertRedirect(route('decrees.index', ['tab' => 'blank']));

        $decree = Decree::query()->firstOrFail();
        $this->assertSame('blank', $decree->category);
        $this->assertNull($decree->person_name);
        $this->assertNull($decree->issued_on);

        $this->actingAs($admin)->patch(route('decrees.update', $decree), [
            'person_name' => 'А.Ариунболд',
            'qty_zahiramj' => 3,
            'num_zahiramj' => '100-102',
        ])->assertRedirect();

        $decree->refresh();
        $this->assertSame('А.Ариунболд', $decree->person_name);
        $this->assertSame(3, $decree->qty_zahiramj);
        $this->assertSame('100-102', $decree->num_zahiramj);
        $this->assertSame('100-102', $decree->blank_number);

        $this->actingAs($admin)->patch(route('decrees.update', $decree), [
            'qty_zahiramj' => null,
            'person_name' => '',
        ])->assertRedirect();

        $decree->refresh();
        $this->assertNull($decree->person_name);
        $this->assertSame(0, $decree->qty_zahiramj);
    }

    public function test_index_includes_phone_directory_people(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        \App\Models\PhoneDirectoryEntry::query()->create([
            'org_name' => 'Удирдлага',
            'category' => 'udirdlaga',
            'person_name' => 'Очирпүрэв Батжаргал',
            'position' => 'Засаг дарга',
            'org_order' => 1,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'blank']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Decrees')
                ->has('people', 1)
                ->where('people.0.label', 'О.Батжаргал'));
    }

    public function test_zahiramj_a_tab_stores_register_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'zahiramj_a',
            'number' => '01',
            'title' => '2026 оныг бүтээмжийн жил болгон зарлах тухай',
            'issued_on' => '2026-01-02',
            'page_count' => 2,
            'attachment_name' => 'Арын бичилт',
            'attachment_pages' => 1,
            'person_name' => 'Б.Зоригтбаатар',
        ])->assertRedirect(route('decrees.index', ['tab' => 'zahiramj_a']));

        $decree = Decree::query()->firstOrFail();
        $this->assertSame('zahiramj', $decree->category);
        $this->assertSame('zahiramj_a', $decree->kind);
        $this->assertSame('01', $decree->number);
        $this->assertSame(2, $decree->page_count);
        $this->assertSame('Арын бичилт', $decree->attachment_name);
        $this->assertSame(1, $decree->attachment_pages);
        $this->assertSame('Б.Зоригтбаатар', $decree->person_name);

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Decrees')
                ->where('tab', 'zahiramj_a')
                ->where('rows.0.number', '01')
                ->where('rows.0.page_count', 2)
                ->where('rows.0.attachment_name', 'Арын бичилт')
                ->where('nextNumber', '02'));
    }

    public function test_new_doc_row_gets_auto_number_when_empty(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Decree::query()->create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '05',
            'title' => 'Өмнөх',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'zahiramj_a',
            'issued_on' => '2026-08-25',
        ])->assertRedirect(route('decrees.index', ['tab' => 'zahiramj_a']));

        $decree = Decree::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame('06', $decree->number);
    }

    public function test_pending_officials_lists_blank_holders_without_decree(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Decree::query()->create([
            'category' => 'blank',
            'kind' => 'blank',
            'person_name' => 'Б.Гантөмөр',
            'qty_zahiramj' => 3,
            'title' => 'Б.Гантөмөр',
            'created_by' => $admin->id,
        ]);
        Decree::query()->create([
            'category' => 'blank',
            'kind' => 'blank',
            'person_name' => 'Б.Зоригтбаатар',
            'qty_zahiramj' => 2,
            'title' => 'Б.Зоригтбаатар',
            'created_by' => $admin->id,
        ]);
        Decree::query()->create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Тест',
            'person_name' => 'Б.Зоригтбаатар',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Decrees')
                ->has('pendingOfficials', 1)
                ->where('pendingOfficials.0.label', 'Б.Гантөмөр'));
    }

    public function test_niit_tab_lists_all_register_kinds(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Decree::query()->create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '1',
            'title' => 'А',
            'created_by' => $admin->id,
        ]);
        Decree::query()->create([
            'category' => 'tushaal',
            'kind' => 'tushaal_b',
            'number' => '2',
            'title' => 'Б',
            'created_by' => $admin->id,
        ]);
        Decree::query()->create([
            'category' => 'blank',
            'kind' => 'blank',
            'title' => 'Бланк',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'niit']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Decrees')
                ->where('tab', 'niit')
                ->has('rows', 2)
                ->where('tabs.5.value', 'niit')
                ->where('tabs.5.count', 2));

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'zahiramj']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tab', 'zahiramj_a'));
    }

    public function test_decree_image_can_be_uploaded_viewed_and_removed(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $decree = Decree::query()->create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Зурагтай',
            'created_by' => $admin->id,
        ]);

        $file = UploadedFile::fake()->image('decree.jpg', 800, 600);

        $this->actingAs($admin)
            ->post(route('decrees.image.upload', $decree), ['image' => $file])
            ->assertRedirect();

        $decree->refresh();
        $this->assertNotNull($decree->file_path);
        Storage::disk('local')->assertExists($decree->file_path);

        $this->actingAs($admin)
            ->get(route('decrees.image.show', $decree))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.has_image', true));

        $path = $decree->file_path;

        $this->actingAs($admin)
            ->delete(route('decrees.image.destroy', $decree))
            ->assertRedirect();

        $decree->refresh();
        $this->assertNull($decree->file_path);
        Storage::disk('local')->assertMissing($path);
    }
}
