<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissingRowHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_deleted_row_redirects_instead_of_404(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Устсан захирамжийг засах гэвэл
        $this->actingAs($admin)
            ->withHeader('X-Inertia', 'true')
            ->patch(route('decrees.update', 999), ['title' => 'Тест'])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Устсан үүрэг
        $this->actingAs($admin)
            ->withHeader('X-Inertia', 'true')
            ->patch(route('tasks.update', 999), ['text' => 'Тест'])
            ->assertRedirect();

        // Устсан утасны бүртгэл
        $this->actingAs($admin)
            ->withHeader('X-Inertia', 'true')
            ->delete(route('phone-directory.destroy', 999))
            ->assertRedirect();
    }

    public function test_api_requests_still_receive_404(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patchJson(route('decrees.update', 999), ['title' => 'Тест'])
            ->assertStatus(404);
    }
}
