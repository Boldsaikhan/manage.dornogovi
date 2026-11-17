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

    public function test_used_award_type_cannot_be_renamed_or_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $type = AwardType::query()->create([
            'name' => 'Ашиглагдаж буй медаль',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Award::query()->create([
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
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('award_types', [
            'id' => $type->id,
            'name' => 'Ашиглагдаж буй медаль',
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
            'nominated_award' => 'Алтан гадас',
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
