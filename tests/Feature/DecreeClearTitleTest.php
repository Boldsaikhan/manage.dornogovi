<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecreeClearTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_title_can_be_emptied_on_document_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $decree = Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => 'A/123',
            'title' => 'Хуучин гарчиг',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('decrees.update', $decree), ['title' => ''])
            ->assertRedirect();

        $this->assertNull($decree->fresh()->title);

        // Дахин бөглөж болно
        $this->actingAs($admin)
            ->patch(route('decrees.update', $decree), ['title' => 'Шинэ гарчиг'])
            ->assertRedirect();

        $this->assertSame('Шинэ гарчиг', $decree->fresh()->title);

        // Төрлийг хоослоход хуучин утга үлдэнэ (NOT NULL багана)
        $this->actingAs($admin)
            ->patch(route('decrees.update', $decree), ['kind' => ''])
            ->assertRedirect();

        $this->assertSame('zahiramj_a', $decree->fresh()->kind);
    }
}
