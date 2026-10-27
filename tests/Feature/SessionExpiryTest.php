<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class SessionExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_can_keep_its_session_alive(): void
    {
        $this->get('/session/keep-alive')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_an_expired_token_sends_a_guest_back_to_login_without_a_warning(): void
    {
        $request = Request::create('/login', 'POST');
        $request->headers->set('X-Inertia', 'true');

        $response = app(ExceptionHandler::class)->render(
            $request,
            new TokenMismatchException('CSRF token mismatch.'),
        );

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringEndsWith('/login', $response->headers->get('Location'));
        $this->assertNull(session('status'));
    }
}
