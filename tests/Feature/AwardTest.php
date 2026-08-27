<?php

namespace Tests\Feature;

use App\Models\Award;
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
            );
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
                'category' => 'other',
                'year' => 2025,
                'award_name' => 'Шинэ нэр',
                'surname' => 'Шинэ',
                'given_name' => 'Нэр',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('awards', [
            'id' => $award->id,
            'award_name' => 'Шинэ нэр',
            'surname' => 'Шинэ',
        ]);

        $this->actingAs($admin)
            ->delete(route('awards.destroy', $award))
            ->assertRedirect();

        $this->assertDatabaseMissing('awards', ['id' => $award->id]);
    }
}
