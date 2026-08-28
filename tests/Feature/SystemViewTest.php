<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use App\Services\EmbedChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemViewTest extends TestCase
{
    use RefreshDatabase;

    private function system(array $attributes = []): System
    {
        return System::create(array_merge([
            'slug' => 'shilen',
            'name' => 'Шилэн данс',
            'url' => 'https://shilen.gov.mn/home',
            'login_url' => 'https://shilen.gov.mn/login',
            'category' => 'Санхүү',
            'is_embeddable' => true,
        ], $attributes));
    }

    public function test_viewer_shows_the_system_entry_url(): void
    {
        $user = User::factory()->create();
        $system = $this->system();
        $system->viewers()->sync([$user->id]);

        $this->actingAs($user)
            ->get(route('systems.show', $system))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Systems/View')
                ->where('target', 'https://shilen.gov.mn/login')
                ->where('system.is_embeddable', true));
    }

    public function test_a_blocked_system_still_renders_with_the_reason(): void
    {
        $user = User::factory()->create();
        $system = $this->system([
            'is_embeddable' => false,
            'embed_blocked_by' => 'X-Frame-Options: SAMEORIGIN',
        ]);
        $system->viewers()->sync([$user->id]);

        $this->actingAs($user)
            ->get(route('systems.show', $system))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('system.is_embeddable', false)
                ->where('system.embed_blocked_by', 'X-Frame-Options: SAMEORIGIN'));
    }

    public function test_inactive_systems_are_not_viewable(): void
    {
        $user = User::factory()->create();
        $system = $this->system(['is_active' => false]);
        $system->viewers()->sync([$user->id]);

        $this->actingAs($user)
            ->get(route('systems.show', $system))
            ->assertNotFound();
    }

    public function test_guests_cannot_use_the_viewer(): void
    {
        $this->get(route('systems.show', $this->system()))->assertRedirect(route('login'));
    }

    public function test_embed_checker_flags_x_frame_options(): void
    {
        Http::fake(['*' => Http::response('', 200, ['X-Frame-Options' => 'SAMEORIGIN'])]);

        $result = app(EmbedChecker::class)->check('https://erp.e-mongolia.mn/login');

        $this->assertFalse($result['embeddable']);
        $this->assertStringContainsString('SAMEORIGIN', $result['blocked_by']);
    }

    public function test_embed_checker_flags_csp_frame_ancestors(): void
    {
        Http::fake([
            '*' => Http::response('', 200, [
                'Content-Security-Policy' => "default-src 'self'; frame-ancestors 'self'",
            ]),
        ]);

        $result = app(EmbedChecker::class)->check('https://www.gov.mn/home');

        $this->assertFalse($result['embeddable']);
        $this->assertStringContainsString('frame-ancestors', $result['blocked_by']);
    }

    public function test_embed_checker_allows_a_site_with_no_framing_headers(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $result = app(EmbedChecker::class)->check('https://shilen.gov.mn/home');

        $this->assertTrue($result['embeddable']);
        $this->assertNull($result['blocked_by']);
    }

    public function test_embed_checker_allows_wildcard_frame_ancestors(): void
    {
        Http::fake(['*' => Http::response('', 200, ['Content-Security-Policy' => 'frame-ancestors *'])]);

        $this->assertTrue(app(EmbedChecker::class)->check('https://a.mn')['embeddable']);
    }

    public function test_embed_checker_records_the_result_on_the_system(): void
    {
        Http::fake(['*' => Http::response('', 200, ['X-Frame-Options' => 'DENY'])]);

        $system = app(EmbedChecker::class)->refresh($this->system());

        $this->assertFalse($system->is_embeddable);
        $this->assertNotNull($system->embed_checked_at);
    }

    public function test_unreachable_site_is_left_unknown(): void
    {
        Http::fake(fn () => throw new \RuntimeException('холбогдсонгүй'));

        $this->assertNull(app(EmbedChecker::class)->check('https://down.example.com')['embeddable']);
    }
}
