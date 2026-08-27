<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('name="csrf-token"', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dept.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_authenticate_using_their_phone_number(): void
    {
        $user = User::factory()->create(['phone' => '99111234']);

        $response = $this->post('/login', [
            'login' => '99111234',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dept.dashboard', absolute: false));
    }

    /**
     * Хэрэглэгч зай, зураас, улсын кодтой бичсэн ч нэвтрэх ёстой.
     */
    public function test_phone_number_is_normalised_before_lookup(): void
    {
        $user = User::factory()->create(['phone' => '99111234']);

        foreach (['9911 1234', '+976 9911-1234', '976 99111234'] as $written) {
            $this->post('/login', [
                'login' => $written,
                'password' => 'password',
            ]);

            $this->assertAuthenticatedAs($user);

            $this->post('/logout');
        }
    }

    public function test_users_can_not_authenticate_with_unknown_phone_number(): void
    {
        User::factory()->create(['phone' => '99111234']);

        $this->post('/login', [
            'login' => '88887777',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_authenticate_using_their_email(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_csrf_mismatch_redirects_back_instead_of_419_page(): void
    {
        $request = Request::create('/login', 'POST', [
            'login' => '99111234',
            'password' => 'secret',
        ]);
        $request->headers->set('Accept', 'text/html');
        $request->setLaravelSession($this->app['session']->driver());

        $response = $this->app->make(ExceptionHandler::class)->render(
            $request,
            new TokenMismatchException(),
        );

        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }
}
