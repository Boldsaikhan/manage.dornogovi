<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use App\Models\UserModulePermission;
use App\Services\Ai\AiSettings;
use App\Services\Ai\AssistantService;
use App\Services\Ai\Tools\SystemTools;
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

    public function test_closed_ai_module_is_omitted_from_briefing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        app(AiSettings::class)->setModuleAccess([
            'general' => 'read',
            'tasks' => 'none',
            'leaves' => 'read',
        ]);

        $briefing = app(SystemTools::class)->dashboardBriefing($admin);
        $modules = collect($briefing['items'])->pluck('module');

        $this->assertFalse($modules->contains('tasks'));
        $this->assertTrue($modules->contains('leaves'));
    }

    public function test_user_cannot_read_module_without_access_rights(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_specialist' => false,
            'is_department_head' => false,
        ]);
        UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'tasks',
            'level' => 'view',
        ]);

        app(AiSettings::class)->setModuleAccess([
            'tasks' => 'read',
            'decrees' => 'read',
        ]);

        $registry = app(ToolRegistry::class);

        $denied = $registry->run('search_orders', $user->fresh(), []);
        $this->assertFalse($denied['ok']);
        $this->assertTrue($denied['denied']);
        $this->assertStringContainsString('харах эрх', $denied['error']);

        $allowed = $registry->run('search_tasks', $user->fresh(), []);
        $this->assertTrue($allowed['ok']);
    }

    public function test_view_own_scopes_task_search_to_related_rows(): void
    {
        $user = User::factory()->create([
            'name' => 'Б.Болдсайхан',
            'is_admin' => false,
            'is_specialist' => false,
            'is_department_head' => false,
        ]);
        UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'tasks',
            'level' => 'view_own',
        ]);

        app(AiSettings::class)->setModuleAccess(['tasks' => 'read']);

        $source = TaskSource::query()->firstOrFail();
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Миний үүрэг',
            'responsible' => 'Б.Болдсайхан',
            'progress' => 10,
            'sort_order' => 1,
        ]);
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Бусдын үүрэг',
            'responsible' => 'П.Гантуяа',
            'progress' => 20,
            'sort_order' => 2,
        ]);

        $result = app(ToolRegistry::class)->run('search_tasks', $user->fresh(), []);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('Миний үүрэг', $result['data']['items'][0]['text']);
    }

    public function test_write_denied_when_user_only_has_view(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_specialist' => false,
            'is_department_head' => false,
        ]);
        UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'leaves',
            'level' => 'view',
        ]);

        app(AiSettings::class)->setModuleAccess(['leaves' => 'write']);

        $prepare = app(ToolRegistry::class)->run('prepare_leave_request', $user->fresh(), [
            'start_date' => '2026-09-01',
            'days' => 1,
        ]);
        $this->assertFalse($prepare['ok']);
        $this->assertTrue($prepare['denied']);

        $confirm = app(AssistantService::class)->confirmAction($user->fresh(), 'CREATE_LEAVE_REQUEST', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'days' => 1,
        ]);
        $this->assertFalse($confirm['ok']);
    }
}
