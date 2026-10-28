<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Профайл — хаанаас нэвтэрсэн байгааг харж, холболтыг таслах.
 */
class ProfileLoginSessionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    private function seedSession(User $user, string $id, string $agent, int $ago = 60): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '192.168.1.5',
            'user_agent' => $agent,
            'payload' => '',
            'last_activity' => time() - $ago,
        ]);
    }

    public function test_the_profile_lists_open_sessions(): void
    {
        $user = User::factory()->create();

        $this->seedSession($user, 'session-a', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/605.1');
        $this->seedSession($user, 'session-b', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0');

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('loginSessions', 2)
                ->where('loginSessions.0.device', 'iPhone')
                ->where('loginSessions.0.browser', 'Safari')
                ->where('loginSessions.1.device', 'Windows')
                ->where('loginSessions.1.browser', 'Chrome'));
    }

    public function test_a_session_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $this->seedSession($user, 'session-a', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.sessions.destroy', 'session-a'))
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('sessions', ['id' => 'session-a']);
    }

    public function test_another_users_session_is_not_touched(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->seedSession($other, 'session-other', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.sessions.destroy', 'session-other'))
            ->assertRedirect();

        $this->assertDatabaseHas('sessions', ['id' => 'session-other']);
    }

    public function test_all_other_sessions_can_be_revoked_at_once(): void
    {
        $user = User::factory()->create();

        $this->seedSession($user, 'session-a', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0');
        $this->seedSession($user, 'session-b', 'Mozilla/5.0 (Macintosh) Safari/605.1');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.sessions.destroy-others'))
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'session-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-b']);
    }

    public function test_a_guest_cannot_revoke_sessions(): void
    {
        $this->delete(route('profile.sessions.destroy-others'))
            ->assertRedirect(route('login'));
    }
}
