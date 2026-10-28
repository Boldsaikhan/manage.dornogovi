<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Системийн алдааны бүртгэл — зөвхөн админ хардаг.
 */
class SystemLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private function writeLog(string $contents): void
    {
        $path = SystemLog::path();

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    public function test_only_errors_are_listed_newest_first(): void
    {
        $this->writeLog(
            "[2026-10-28 10:00:00] production.INFO: Энгийн мэдээлэл\n"
            ."[2026-10-28 11:00:00] production.ERROR: Эхний алдаа\n#0 /app/foo.php(12)\n"
            ."[2026-10-28 12:00:00] production.ERROR: Хоёр дахь алдаа\n#0 /app/bar.php(34)\n"
        );

        $entries = SystemLog::recent();

        $this->assertCount(2, $entries);
        $this->assertSame('Хоёр дахь алдаа', $entries[0]['message']);
        $this->assertSame('Эхний алдаа', $entries[1]['message']);
        $this->assertStringContainsString('bar.php', $entries[0]['trace']);
    }

    public function test_an_admin_can_open_the_page(): void
    {
        $this->writeLog("[2026-10-28 12:00:00] production.ERROR: Алдаа\n");

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.logs'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/SystemLogs')
                ->has('entries', 1));
    }

    public function test_a_normal_user_cannot_open_it(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.logs'))
            ->assertForbidden();
    }

    public function test_a_missing_log_file_is_not_an_error(): void
    {
        $path = SystemLog::path();

        if (is_file($path)) {
            unlink($path);
        }

        $this->assertSame([], SystemLog::recent());
    }
}
