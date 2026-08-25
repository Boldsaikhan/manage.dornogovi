<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecreePrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_page_renders_visible_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Тест захирамж',
            'issued_on' => '2026-08-25',
            'person_name' => 'Б.Гантөмөр',
            'created_by' => $admin->id,
        ]);

        // Өөр табын мөр — хэвлэх хуудсанд орохгүй
        Decree::create([
            'category' => 'tushaal',
            'kind' => 'tushaal_a',
            'number' => '77',
            'title' => 'Өөр табын тушаал',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('decrees.print', ['tab' => 'zahiramj_a']))
            ->assertOk()
            ->assertSee('Аймгийн Засаг даргын Захирамжийн бүртгэл (А)')
            ->assertSee('Тест захирамж')
            ->assertSee('Б.Гантөмөр')
            ->assertDontSee('Өөр табын тушаал');

        // Бланкны таб — өөр толгойтой
        $this->actingAs($admin)
            ->get(route('decrees.print', ['tab' => 'blank']))
            ->assertOk()
            ->assertSee('Хэвлэмэл хуудасны бүртгэл')
            ->assertSee('Үрэгдүүлсэн хуудасны дугаар');
    }
}
