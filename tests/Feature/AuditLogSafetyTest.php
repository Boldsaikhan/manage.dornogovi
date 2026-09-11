<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Лог бичих нь хэрэглэгчийн ажлыг зогсоохгүй байх.
 */
class AuditLogSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_broken_log_table_does_not_break_saving(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = TaskSource::query()->firstOrCreate(
            ['key' => 'directive'],
            ['name' => 'Үүрэг чиглэл', 'layout' => 'directive'],
        );

        $task = $source->tasks()->create(['text' => '', 'sort_order' => 1, 'progress' => 0]);

        // Лог бичих боломжгүй болгоё — хадгалалт нь үргэлжлэх ёстой.
        Schema::drop('audit_logs');

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->patch(route('tasks.update', $task), ['text' => 'Хадгалагдах ёстой'])
            ->assertRedirect();

        $this->assertSame('Хадгалагдах ёстой', $task->fresh()->text);
    }

    public function test_long_values_are_cut_to_fit(): void
    {
        AuditLog::record(
            modelType: 'task',
            modelId: 1,
            action: 'updated',
            scope: str_repeat('а', 200),
            label: str_repeat('б', 900),
            changes: [str_repeat('в', 300) => ['from' => '', 'to' => str_repeat('г', 4000)]],
        );

        $log = AuditLog::query()->firstOrFail();

        $this->assertSame(64, mb_strlen($log->scope));
        $this->assertSame(240, mb_strlen($log->label));

        $field = array_key_first($log->changes);
        $this->assertSame(120, mb_strlen($field));
        $this->assertSame(500, mb_strlen($log->changes[$field]['to']));
    }

    public function test_broken_characters_do_not_stop_the_log(): void
    {
        AuditLog::record(
            modelType: 'task',
            modelId: 2,
            action: 'updated',
            // Word-оос хуулахад ийм эвдэрсэн байт таарч болно.
            label: "Үүрэг\xB1\xC0 чиглэл",
            changes: ['Тэмдэглэл' => ['from' => '', 'to' => "Эвдэрсэн\xFF"]],
        );

        $log = AuditLog::query()->where('model_id', 2)->firstOrFail();

        $this->assertStringContainsString('Үүрэг', $log->label);
        $this->assertStringContainsString('Эвдэрсэн', $log->changes['Тэмдэглэл']['to']);
    }
}
