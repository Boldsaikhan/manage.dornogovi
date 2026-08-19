<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_landing_page_with_active_systems(): void
    {
        System::create([
            'slug' => 'idevhtei',
            'name' => 'Идэвхтэй систем',
            'url' => 'https://example.mn',
            'category' => 'Санхүү',
            'is_active' => true,
        ]);

        System::create([
            'slug' => 'idevhgui',
            'name' => 'Идэвхгүй систем',
            'url' => 'https://example.mn',
            'category' => 'Санхүү',
            'is_active' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Welcome')
                ->has('systems', 1)
                ->where('systems.0.name', 'Идэвхтэй систем')
            );
    }

    public function test_authenticated_user_is_redirected_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }
}
