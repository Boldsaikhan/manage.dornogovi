<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Өгөгдлийн сангийн нөөц / үзүүлэлт — зөвхөн админ.
 */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_open_the_page(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.database.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/DatabaseBackup')
                ->has('stats')
                ->has('stats.tables')
                ->where('stats.driver', 'sqlite')
                ->where('stats.table_count', fn ($count) => $count > 0));
    }

    public function test_a_normal_user_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.database.index'))
            ->assertForbidden();
    }

    public function test_a_guest_cannot_open_the_page(): void
    {
        $this->get(route('admin.database.index'))
            ->assertRedirect();
    }

    public function test_an_admin_can_download_a_sql_dump(): void
    {
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.database.download'));

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.sql', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('database backup', $content);
        $this->assertStringContainsString('CREATE TABLE', $content);
    }

    public function test_a_normal_user_cannot_download_a_dump(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.database.download'))
            ->assertForbidden();
    }

    public function test_stats_list_tables_for_sqlite(): void
    {
        $stats = DatabaseBackup::stats();

        $this->assertSame('sqlite', $stats['driver']);
        $this->assertGreaterThan(0, $stats['table_count']);
        $this->assertNotEmpty($stats['tables']);
        $this->assertArrayHasKey('name', $stats['tables'][0]);
        $this->assertArrayHasKey('rows', $stats['tables'][0]);
    }
}
