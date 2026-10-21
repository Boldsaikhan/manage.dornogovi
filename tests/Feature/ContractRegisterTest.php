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
