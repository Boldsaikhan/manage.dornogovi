<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\AiSettings;
use App\Services\Ai\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_and_registry_enforces(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.ai-settings.update'), [
            'enabled' => true,
            'display_name' => 'Manage AI',
            'provider' => 'local',
            'openai_model' => 'gpt-4o-mini',
            'daily_question_limit' => 60,
            'module_access' => [
                'tasks' => 'none',
                'leaves' => 'write',
                'decrees' => 'read',
            ],
        ])->assertRedirect();

        $settings = app(AiSettings::class);
        $this->assertSame('none', $settings->accessFor('tasks'));
        $this->assertTrue($settings->canWrite('leaves'));
        $this->assertFalse($settings->canWrite('decrees'));

        $registry = app(ToolRegistry::class);

        // Хаалттай цэс — AI хандахгүй
        $blocked = $registry->run('search_tasks', $admin, []);
        $this->assertFalse($blocked['ok']);
        $this->assertTrue($blocked['denied']);

        // Зөвхөн харах цэс — хайлт ажиллана
        $allowed = $registry->run('search_orders', $admin, []);
        $this->assertTrue($allowed['ok']);

        // Бичих эрх өгсөн цэс — бэлтгэх хэрэгсэл ажиллана
        $write = $registry->run('prepare_leave_request', $admin, ['start_date' => '2026-09-01', 'days' => 1]);
        $this->assertTrue($write['ok']);
    }

    public function test_write_denied_when_only_read(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        app(AiSettings::class)->setModuleAccess(['leaves' => 'read']);

        $result = app(ToolRegistry::class)->run('prepare_leave_request', $admin, ['start_date' => '2026-09-01', 'days' => 1]);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['denied']);
    }
}
