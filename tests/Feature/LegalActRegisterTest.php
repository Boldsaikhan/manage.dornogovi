<?php

namespace Tests\Feature;

use App\Models\LegalAct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * «Эрх зүйн акт, тогтоол» — салбарын хууль, тогтоолын нэгдсэн бүртгэл.
 */
class LegalActRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_register_opens_with_its_tabs_and_source_link(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('legal-acts.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $props = $page->toArray()['props'];

                $this->assertSame('legal_acts', $props['module']);

                $this->assertSame(
                    [
                        'Нийт', 'Хууль', 'УИХ-ын тогтоол', 'Засгийн газрын тогтоол',
                        'Сайдын тушаал', 'Орон нутгийн', 'Бусад', 'Суурь мэдээлэл',
                    ],
                    collect($props['scopeTabs'])->pluck('label')->all(),
                );

                // Табууд хоёр бүлэгт хуваагдана.
                $this->assertSame(
                    ['Хууль тогтоомж', 'Салбарын үзүүлэлт'],
                    collect($props['scopeTabs'])->pluck('group_label')->unique()->values()->all(),
                );

                // legalinfo.mn руу шууд очих холбоос.
                $this->assertSame('https://legalinfo.mn/mn', $props['externalLink']['url']);
            });
    }

    public function test_a_blank_row_is_added_and_filled_cell_by_cell(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('modules.store', ['module' => 'legal_acts', 'scope' => 'uih']), ['blank' => true])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = LegalAct::query()->sole();
        $this->assertSame('uih', $row->scope);

        foreach ([
            'title' => 'Төрийн албаны тухай хууль',
            'number' => '2026/41',
            'approved_on' => '2026-05-10',
            'unit_name' => 'Төрийн захиргааны удирдлагын хэлтэс',
            'source_url' => 'https://legalinfo.mn/mn/detail/123',
        ] as $field => $value) {
            $this->actingAs($admin)
                ->post(route('modules.field', ['module' => 'legal_acts', 'id' => $row->id]), [
                    'field' => $field,
                    'value' => $value,
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $fresh = $row->fresh();
        $this->assertSame('Төрийн албаны тухай хууль', $fresh->title);
        $this->assertSame('https://legalinfo.mn/mn/detail/123', $fresh->source_url);
        $this->assertSame('2026-05-10', $fresh->approved_on->format('Y-m-d'));
    }

    public function test_a_tab_shows_only_its_own_acts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        LegalAct::query()->create(['scope' => 'huuli', 'title' => 'Хууль']);
        LegalAct::query()->create(['scope' => 'oron_nutag', 'title' => 'Орон нутгийн акт']);

        $this->actingAs($admin)
            ->get(route('legal-acts.index', ['scope' => 'oron_nutag']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
                ->where('rows.0.title', 'Орон нутгийн акт'));
    }

    public function test_a_user_without_access_cannot_open_it(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('legal-acts.index'))
            ->assertForbidden();
    }
}
