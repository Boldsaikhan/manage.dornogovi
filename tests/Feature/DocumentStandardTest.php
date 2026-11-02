<?php

namespace Tests\Feature;

use App\Models\DocumentStandard;
use App\Models\User;
use App\Support\DocumentStandardChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DocumentStandardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $this->get(route('doc-standards.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_current_procedure_and_hides_repealed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('doc-standards.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/DocStandards')
                ->has('standards')
                ->where('standards', function ($rows) {
                    $titles = collect($rows)->pluck('title');

                    return $titles->contains('Албан бичиг')
                        && ! $titles->contains('2020 оны 246 дугаар тогтоол');
                }));
    }

    public function test_repealed_filter_shows_the_old_procedure(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('doc-standards.index', ['status' => 'repealed']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('standards.0.title', '2020 оны 246 дугаар тогтоол')
                ->where('standards.0.status', 'repealed'));
    }

    public function test_show_page_cites_the_current_resolution(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('doc-standards.show', 'official-letter'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/DocStandards/Show')
                ->where('standard.source_number', 'ЗГ-ын 2024.12.18-ны 214')
                ->where('standard.status', 'in_force'));
    }

    public function test_checker_flags_copied_letterhead_and_ignores_repealed_rules(): void
    {
        $checker = app(DocumentStandardChecker::class);

        $hit = $checker->inspect('Хэвлэмэл хуудсыг хувилагч машинаар олшруулав.', 'letter', 'official-letter');
        $this->assertSame('8.1.11', $hit['findings'][0]['clause']);

        $old = $checker->inspect('3,653,000 төгрөг', 'letter', 'repealed-2020');
        $this->assertSame([], $old['findings']);
        $this->assertSame([], $old['checklist']);
        $this->assertNotSame([], $old['skipped']);
    }

    public function test_official_standard_is_archived_not_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $standard = DocumentStandard::query()->where('slug', 'official-letter')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('document-standards.destroy', $standard))
            ->assertStatus(422);

        $this->assertDatabaseHas('document_standards', ['id' => $standard->id]);

        $this->actingAs($admin)
            ->post(route('document-standards.archive', $standard))
            ->assertRedirect();

        $this->assertNotNull($standard->fresh()->archived_at);
    }

    public function test_manager_can_add_a_sourced_standard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $categoryId = \App\Models\DocumentStandardCategory::query()->where('key', 'letter')->value('id');

        $this->actingAs($admin)
            ->post(route('document-standards.store'), [
                'category_id' => $categoryId,
                'title' => 'Байгууллагын нэмэлт журам',
                'code' => '1.7',
                'summary' => 'Дотоод журам.',
                'body' => '1.7-д нийцүүлэн баталсан.',
                'source_title' => 'Албан хэрэг хөтлөлтийн нийтлэг журам',
                'source_url' => 'https://legalinfo.mn/mn/detail?lawId=17430876112092',
                'source_number' => 'ЗГ-ын 2024.12.18-ны 214',
                'status' => 'in_force',
                'sort_order' => 200,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('document_standards', [
            'title' => 'Байгууллагын нэмэлт журам',
            'is_official' => 0,
        ]);
    }

    public function test_ai_check_uses_the_current_standard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('ai.ask'), [
                'message' => 'Энэ албан бичгийн төслийг стандартаар шалга: хэвлэмэл хуудсыг хувилагч машинаар олшруулав.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_messages', [
            'user_id' => $admin->id,
            'role' => 'assistant',
        ]);

        $answer = \App\Models\AiMessage::query()
            ->where('user_id', $admin->id)
            ->where('role', 'assistant')
            ->value('content');

        $this->assertStringContainsString('8.1.11', (string) $answer);
    }
}
