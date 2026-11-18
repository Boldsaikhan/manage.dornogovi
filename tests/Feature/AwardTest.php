<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\AwardType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_awards_index(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Award::query()->create([
            'category' => 'state_high',
            'year' => 2021,
            'surname' => 'Бат',
            'given_name' => 'Дорж',
            'nominated_award' => 'ХГҮТО',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('awards.index', ['tab' => 'state_high']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Awards')
                ->where('tab', 'state_high')
                ->has('rows', 1)
                ->has('awardTypes')
                ->has('supportingOrgs', 2)
                ->where('supportingOrgs.0.value', 'Ерөнхийлөгчийн Тамгын газар')
                ->where('supportingOrgs.1.value', 'Аймгийн Иргэдийн Төлөөлөгчдийн Хурал')
            );
    }

    public function test_supporting_org_is_chosen_from_the_two_bodies(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $award = Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'Бат',
            'given_name' => 'Дорж',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('awards.update', $award), [
                'supporting_org' => 'Ерөнхийлөгчийн Тамгын газар',
            ])
            ->assertRedirect();

        $this->assertSame('Ерөнхийлөгчийн Тамгын газар', $award->fresh()->supporting_org);

        $this->actingAs($admin)
            ->patch(route('awards.update', $award), [
                'supporting_org' => 'Аймгийн Иргэдийн Төлөөлөгчдийн Хурал',
            ])
            ->assertRedirect();

        $this->assertSame('Аймгийн Иргэдийн Төлөөлөгчдийн Хурал', $award->fresh()->supporting_org);

        $this->actingAs($admin)
            ->patch(route('awards.update', $award), [
                'supporting_org' => 'Өөр байгууллага',
            ])
            ->assertSessionHasErrors('supporting_org');
    }

    public function test_register_number_fills_age_and_gender(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $award = Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'Бат',
            'given_name' => 'Дорж',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('awards.update', $award), [
                'register_no' => 'УБ85031512',
            ])
            ->assertRedirect();

        $award->refresh();

        $this->assertSame('УБ85031512', $award->register_no);
        $this->assertSame(41, $award->age);
        $this->assertSame('эр', $award->gender);
    }

    public function test_register_number_normalizes_spaces_and_latin_letters(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $award = Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'Өргөнцагаан',
            'given_name' => 'Амарсанаа',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('awards.update', $award), [
                'register_no' => 'EC 72021813',
            ])
            ->assertRedirect();

        $award->refresh();

        $this->assertSame('ЕЦ72021813', $award->register_no);
        $this->assertSame(54, $award->age);
        $this->assertSame('эр', $award->gender);
    }

    public function test_award_name_normalizer_fixes_mojibake_and_aliases(): void
    {
        $names = app(\App\Support\AwardNameNormalizer::class);

        $this->assertSame(
            'Монгол улсын төрийн шагнал',
            $names->canonicalize('Ìîíãîë óëñûí òºðèéí øàãíàë'),
        );
        $this->assertSame('Алтан гадас одон', $names->canonicalize('АГО'));
        $this->assertSame('Алтан гадас одон', $names->canonicalize('Алтангадас” одон'));
        $this->assertSame('Хөдөлмөрийн хүндэт медаль', $names->canonicalize('ХХМ'));
        $this->assertSame('Цэргийн гавьяаны одон', $names->canonicalize('Öýðãèéí ãàâúÿàíû îäîí'));
    }

    public function test_repair_award_names_merges_duplicates(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        AwardType::query()->create(['name' => 'АГО', 'sort_order' => 100, 'is_active' => true]);
        AwardType::query()->create(['name' => 'Алтангадас одон', 'sort_order' => 110, 'is_active' => true]);
        AwardType::query()->create(['name' => 'Алтан гадас одон', 'sort_order' => 120, 'is_active' => true]);
        AwardType::query()->create(['name' => 'Ìîíãîë óëñûí õºäºëìºðèéí áààòàð', 'sort_order' => 130, 'is_active' => true]);

        $award = Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'Тест',
            'given_name' => 'Нэр',
            'nominated_award' => 'АГО',
            'last_award' => 'Алтангадас одон',
            'created_by' => $admin->id,
        ]);

        $this->artisan('awards:repair-names')->assertSuccessful();

        $award->refresh();
        $this->assertSame('Алтан гадас одон', $award->nominated_award);
        $this->assertSame('Алтан гадас одон', $award->last_award);

        $this->assertSame(1, AwardType::query()->where('name', 'Алтан гадас одон')->count());
        $this->assertDatabaseMissing('award_types', ['name' => 'АГО']);
        $this->assertDatabaseMissing('award_types', ['name' => 'Алтангадас одон']);
        $this->assertDatabaseHas('award_types', ['name' => 'Монгол Улсын Хөдөлмөрийн баатар']);
    }

    public function test_register_penultimate_digit_sets_male_gender(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $award = Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'Тест',
            'given_name' => 'Хүн',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('awards.update', $award), [
                'register_no' => 'УХ92092534',
            ])
            ->assertRedirect();

        $award->refresh();

        $this->assertSame('УХ92092534', $award->register_no);
        $this->assertSame(34, $award->age);
        $this->assertSame('эр', $award->gender);
    }

    public function test_rows_are_numbered_descending(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['А', 'Б', 'В'] as $name) {
            Award::query()->create([
                'category' => 'state_high',
                'year' => 2026,
                'surname' => $name,
                'given_name' => 'Тест',
                'created_by' => $admin->id,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('awards.index', ['tab' => 'state_high']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Awards')
                ->where('rows.0.no', 3)
                ->where('rows.1.no', 2)
                ->where('rows.2.no', 1)
            );
    }

    public function test_admin_can_manage_award_types(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('awards.types.store'), [
                'name' => 'Шинэ туршилтын медаль',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('award_types', [
            'name' => 'Шинэ туршилтын медаль',
        ]);

        $type = AwardType::query()->where('name', 'Шинэ туршилтын медаль')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('awards.types.destroy', $type))
            ->assertRedirect();

        $this->assertDatabaseMissing('award_types', ['id' => $type->id]);
    }

    public function test_delete_award_types_after_nairamdal(): void
    {
        $anchor = AwardType::query()->where('name', 'Найрамдал медаль')->firstOrFail();

        AwardType::query()->create([
            'name' => 'Устгах ёстой нэр А',
            'sort_order' => $anchor->sort_order + 1,
            'is_active' => true,
        ]);

        AwardType::query()->create([
            'name' => 'Устгах ёстой нэр Б',
            'sort_order' => $anchor->sort_order + 2,
            'is_active' => true,
        ]);

        // Ашиглагдаж байсан ч каталогоос хасна — мөрөн дэх текст үлдэнэ.
        $used = AwardType::query()->create([
            'name' => 'Устгах ёстой ашигласан',
            'sort_order' => $anchor->sort_order + 3,
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'Бат',
            'given_name' => 'Дорж',
            'last_award' => $used->name,
            'created_by' => $admin->id,
        ]);

        $deleted = AwardType::deleteAfter('Найрамдал медаль');

        $this->assertSame(3, $deleted);
        $this->assertDatabaseHas('award_types', ['name' => 'Найрамдал медаль']);
        $this->assertDatabaseHas('award_types', ['name' => 'Цэргийн хүндэт медаль']);
        $this->assertDatabaseMissing('award_types', ['name' => 'Устгах ёстой нэр А']);
        $this->assertDatabaseMissing('award_types', ['name' => 'Устгах ёстой ашигласан']);
        $this->assertDatabaseHas('awards', ['last_award' => 'Устгах ёстой ашигласан']);
    }

    public function test_used_award_type_can_be_renamed_but_not_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $type = AwardType::query()->create([
            'name' => 'Ашиглагдаж буй медаль',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $award = Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'Бат',
            'given_name' => 'Дорж',
            'nominated_award' => 'Ашиглагдаж буй медаль',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('awards.index', ['tab' => 'state_high']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Awards')
                ->has('awardTypeRows', fn ($rows) => $rows
                    ->where('0.name', 'Ашиглагдаж буй медаль')
                    ->where('0.in_use', true)
                    ->etc()
                )
            );

        $this->actingAs($admin)
            ->patch(route('awards.types.update', $type), [
                'name' => 'Өөр нэр',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('award_types', [
            'id' => $type->id,
            'name' => 'Өөр нэр',
        ]);

        $this->assertDatabaseHas('awards', [
            'id' => $award->id,
            'nominated_award' => 'Өөр нэр',
        ]);

        $this->actingAs($admin)
            ->delete(route('awards.types.destroy', $type))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('award_types', [
            'id' => $type->id,
        ]);
    }

    public function test_can_store_state_high_and_governor_honor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('awards.store'), [
                'category' => 'state_high',
                'year' => 2021,
                'surname' => 'Болд',
                'given_name' => 'Сараа',
                'register_no' => 'УБ99112233',
                'age' => 45,
                'gender' => 'эм',
                'nominated_award' => 'АГО',
                'years_in_country' => 20,
                'years_in_sector' => 15,
                'award_date' => '2021-12-23',
                'resolution_number' => '01',
                'position' => 'Сумын эмнэлгийн эрхлэгч',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('awards', [
            'category' => 'state_high',
            'surname' => 'Болд',
            'nominated_award' => 'АГО',
        ]);

        $this->actingAs($admin)
            ->post(route('awards.store'), [
                'category' => 'governor_honor',
                'subtype' => 'juukh',
                'year' => 2026,
                'surname' => 'Ган',
                'given_name' => 'Баатар',
                'register_no' => 'EO66111176',
                'work_sector' => 'Эрүүл мэнд',
                'job_title' => 'Айраг сумын ЭМТ-ийн эрхлэгч',
                'total_years' => 25,
                'position_years' => 5,
                'order_ref' => 'A/219',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('awards', [
            'category' => 'governor_honor',
            'subtype' => 'juukh',
            'order_ref' => 'A/219',
        ]);
    }

    public function test_governor_honor_requires_subtype(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('awards.store'), [
                'category' => 'governor_honor',
                'year' => 2026,
                'surname' => 'Тест',
                'given_name' => 'Хүн',
            ])
            ->assertSessionHasErrors('subtype');
    }

    public function test_export_xlsx(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Award::query()->create([
            'category' => 'governor_leading',
            'subtype' => 'employee',
            'year' => 2026,
            'surname' => 'Экспорт',
            'given_name' => 'Шалгалт',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('awards.export', ['tab' => 'governor_leading', 'format' => 'xlsx']));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );
        $this->assertSame('PK', substr($response->getContent(), 0, 2));
    }

    public function test_admin_can_import_award_entries(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('awards.import.store'), [
                'tab' => 'state_high',
                'year' => 2026,
                'entries' => [
                    [
                        'surname' => 'Импорт',
                        'given_name' => 'Шалгалт',
                        'register_no' => 'УХ92092534',
                        'nominated_award' => 'Алтан гадас',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('awards', [
            'category' => 'state_high',
            'surname' => 'Импорт',
            'given_name' => 'Шалгалт',
            'register_no' => 'УХ92092534',
            'age' => 34,
            'gender' => 'эр',
            // Import үед нэрийг canonical болгоно.
            'nominated_award' => 'Алтан гадас одон',
        ]);
    }

    public function test_last_award_date_accepts_year_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $award = Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'Бат',
            'given_name' => 'Дорж',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('awards.update', $award), [
                'last_award_date' => '2005',
            ])
            ->assertRedirect();

        $award->refresh();
        $this->assertSame('2005-01-01', optional($award->last_award_date)?->format('Y-m-d'));

        $this->actingAs($admin)
            ->get(route('awards.index', ['tab' => 'state_high', 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.last_award_date', '2005')
                ->where('rows.0.last_award_date_display', '2005')
            );
    }

    public function test_last_award_name_strips_year_into_date_column(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $award = Award::query()->create([
            'category' => 'state_high',
            'year' => 2024,
            'surname' => 'Бат',
            'given_name' => 'Дорж',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('awards.update', $award), [
                'last_award' => 'ХХМ - 2020 он',
            ])
            ->assertRedirect();

        $award->refresh();
        $this->assertSame('Хөдөлмөрийн хүндэт медаль', $award->last_award);
        $this->assertSame('2020-01-01', optional($award->last_award_date)?->format('Y-m-d'));

        [$name, $date] = app(\App\Support\AwardNameNormalizer::class)
            ->splitLastAward('АХ-ын 100 жилийн ойн медаль 2021 он');
        $this->assertSame('АХ-ын 100 жилийн ойн медаль', $name);
        $this->assertSame('2021-01-01', $date);
    }

    public function test_received_status_column_reflects_award_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'Хоосон',
            'given_name' => 'Мөр',
            'created_by' => $admin->id,
        ]);

        Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'Бөглөсөн',
            'given_name' => 'Мөр',
            'received_award' => 'Алтан гадас одон',
            'created_by' => $admin->id,
        ]);

        Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'ЗөвхөнОгноо',
            'given_name' => 'Мөр',
            'award_date' => '2024-05-01',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('awards.index', ['tab' => 'state_high', 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows', fn ($rows) => collect($rows)->firstWhere('surname', 'Хоосон')['received_status'] === 'аваагүй'
                    && collect($rows)->firstWhere('surname', 'Бөглөсөн')['received_status'] === 'авсан'
                    && collect($rows)->firstWhere('surname', 'ЗөвхөнОгноо')['received_status'] === 'авсан')
                ->where('columns', fn ($columns) => collect($columns)->contains(
                    fn ($col) => ($col['key'] ?? null) === 'received_status'
                ))
            );

        $this->actingAs($admin)
            ->get(route('awards.index', [
                'tab' => 'state_high',
                'year' => 'all',
                'f' => ['received_status' => 'аваагүй'],
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totalMatched', 1)
                ->where('rows.0.surname', 'Хоосон')
                ->where('rows.0.received_status', 'аваагүй')
            );
    }

    public function test_year_all_empty_column_search_finds_blank_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Award::query()->create([
            'category' => 'state_high',
            'year' => 2024,
            'surname' => 'ХоосонОвог',
            'given_name' => 'Нэр',
            'profession' => null,
            'created_by' => $admin->id,
        ]);

        Award::query()->create([
            'category' => 'state_high',
            'year' => 2025,
            'surname' => 'Бөглөсөн',
            'given_name' => 'Нэр',
            'profession' => 'Багш',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('awards.index', [
                'tab' => 'state_high',
                'year' => 'all',
                'f' => ['profession' => '-'],
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totalMatched', 1)
                ->where('rows.0.surname', 'ХоосонОвог')
                ->where('filters.profession', '-')
            );

        $this->actingAs($admin)
            ->get(route('awards.index', [
                'tab' => 'state_high',
                'year' => 'all',
                'f' => ['profession' => 'хоосон'],
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totalMatched', 1)
                ->where('rows.0.surname', 'ХоосонОвог')
            );
    }

    public function test_year_all_column_search_covers_all_years(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Award::query()->create([
            'category' => 'state_high',
            'year' => 2020,
            'surname' => 'Хуучин',
            'given_name' => 'Оноос',
            'last_award_date' => '2005-01-01',
            'created_by' => $admin->id,
        ]);

        Award::query()->create([
            'category' => 'state_high',
            'year' => 2026,
            'surname' => 'Шинэ',
            'given_name' => 'Оноос',
            'last_award_date' => '2019-01-01',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('awards.index', [
                'tab' => 'state_high',
                'year' => 'all',
                'f' => ['last_award_date_display' => '2005'],
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('yearAll', true)
                ->where('totalMatched', 1)
                ->has('rows', 1)
                ->where('rows.0.surname', 'Хуучин')
                ->where('filters.last_award_date_display', '2005')
            );

        $this->actingAs($admin)
            ->get(route('awards.index', [
                'tab' => 'state_high',
                'year' => 'all',
                'f' => ['surname' => 'Шинэ'],
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totalMatched', 1)
                ->where('rows.0.surname', 'Шинэ')
            );
    }

    public function test_can_update_and_destroy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $award = Award::query()->create([
            'category' => 'other',
            'year' => 2025,
            'award_name' => 'Хүндэт тэмдэг',
            'surname' => 'Хуучин',
            'given_name' => 'Нэр',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('awards.update', $award), [
                'surname' => 'Шинэ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('awards', [
            'id' => $award->id,
            'surname' => 'Шинэ',
        ]);

        $this->actingAs($admin)
            ->patch(route('awards.update', $award), [
                'category' => 'other',
                'year' => 2025,
                'award_name' => 'Шинэ нэр',
                'surname' => 'Шинэ',
                'given_name' => 'Нэр',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('awards.destroy', $award))
            ->assertRedirect();

        $this->assertDatabaseMissing('awards', ['id' => $award->id]);
    }
}
