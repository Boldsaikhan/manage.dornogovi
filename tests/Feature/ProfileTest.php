<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_cannot_be_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response->assertForbidden();

        $user->refresh();

        $this->assertSame('Original Name', $user->name);
        $this->assertSame('original@example.com', $user->email);
    }

    public function test_users_cannot_self_delete_account_from_profile(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ])
            ->assertForbidden();

        $this->assertNotNull($user->fresh());
    }

    public function test_even_admin_cannot_self_delete_via_profile(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this
            ->actingAs($admin)
            ->delete('/profile', [
                'password' => 'password',
            ])
            ->assertForbidden();

        $this->assertNotNull($admin->fresh());
    }
}
