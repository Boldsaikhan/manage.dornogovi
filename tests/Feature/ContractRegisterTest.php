<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * «Гэрээний дугаар» — албан ёсны бүртгэлийн маягтын багана, дараалал.
 */
class ContractRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_table_header_follows_the_official_register(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('contracts.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $labels = collect($page->toArray()['props']['columns'])->pluck('label')->all();

                $this->assertSame([
                    'Гэрээний нэр, утга агуулга',
                    'Гэрээ байгуулсан он сар',
                    'Гэрээний дугаар',
                    'Хувь',
                    'Боловсруулсан албан тушаалтан',
                    'Хянаж зөвлөгөө өгсөн тэмдэглэл',
                ], $labels);

                $this->assertSame('№', $page->toArray()['props']['rowNumberLabel']);
            });
    }

    public function test_the_register_is_split_by_who_approved_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Contract::query()->create([
            'scope' => 'zasag_darga',
            'title' => 'Засаг даргын гэрээ',
            'number' => '2026/1',
            'issued_on' => '2026-02-01',
        ]);

        Contract::query()->create([
            'scope' => 'tamgyn_darga',
            'title' => 'Тамгын даргын гэрээ',
            'number' => '2026/2',
            'issued_on' => '2026-02-02',
        ]);

        $this->actingAs($admin)
            ->get(route('contracts.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $labels = collect($page->toArray()['props']['scopeTabs'])->pluck('label')->all();

                $this->assertSame(['Нийт', 'Засаг дарга', 'Тамгын дарга', 'Бусад'], $labels);
            });

        // Таб сонгоход зөвхөн тэр бүлгийн гэрээ харагдана.
        $this->actingAs($admin)
            ->get(route('contracts.index', ['scope' => 'tamgyn_darga']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
                ->where('rows.0.title', 'Тамгын даргын гэрээ'));
    }

    public function test_a_new_contract_lands_in_the_open_tab(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('modules.store', ['module' => 'contracts', 'scope' => 'busad']), [
                'title' => 'Бусад гэрээ',
                'issued_on' => '2026-04-01',
                'number' => '2026/9',
            ])
            ->assertRedirect();

        $this->assertSame('busad', Contract::query()->sole()->scope);
    }

    public function test_the_new_columns_are_stored_and_editable(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('modules.store', ['module' => 'contracts']), [
                'title' => 'Цэвэрлэгээний үйлчилгээний гэрээ',
                'issued_on' => '2026-03-05',
                'number' => '2026/41',
                'copies' => 3,
                'prepared_by' => 'Б.Сэргэлэнбаяр',
                'note' => 'Хууль зүйн хэлтэс хянав.',
            ])
            ->assertRedirect();

        $contract = Contract::query()->sole();

        $this->assertSame(3, (int) $contract->copies);
        $this->assertSame('Б.Сэргэлэнбаяр', $contract->prepared_by);

        // Нүдэн дээр нь шууд засна.
        $this->actingAs($admin)
            ->post(route('modules.field', ['module' => 'contracts', 'id' => $contract->id]), [
                'field' => 'copies',
                'value' => '5',
            ])
            ->assertRedirect();

        $this->assertSame(5, (int) $contract->fresh()->copies);
    }
}
