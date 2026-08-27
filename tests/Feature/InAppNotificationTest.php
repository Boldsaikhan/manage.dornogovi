<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Push\EmployeePushNotifier;
use App\Services\Push\WebPushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifier_persists_even_when_web_push_disabled(): void
    {
        $user = User::factory()->create(['name' => 'Б.Болд']);

        $mock = Mockery::mock(WebPushNotifier::class);
        $mock->shouldReceive('sendToUsers')->once()->andReturnNull();
        $this->app->instance(WebPushNotifier::class, $mock);

        app(EmployeePushNotifier::class)->notifyNamed('Б.Болд', [
            'title' => 'Шинэ үүрэг',
            'body' => 'Тест бие',
            'url' => '/uureg',
            'tag' => 'task-1',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'title' => 'Шинэ үүрэг',
            'tag' => 'task-1',
        ]);
    }

    public function test_inbox_lists_and_syncs_open_tasks(): void
    {
        $user = User::factory()->create([
            'name' => 'Батбаярын Дулмаа',
            'is_admin' => true,
        ]);

        $source = TaskSource::where('key', TaskSource::KEY_DIRECTIVE)->first();
        Task::create([
            'task_source_id' => $source->id,
            'text' => 'Миний нээлттэй үүрэг',
            'responsible' => 'Б.Дулмаа',
            'progress' => 40,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonFragment(['title' => 'Үүрэг даалгавар']);
    }

    public function test_mark_read_and_clear(): void
    {
        $user = User::factory()->create();
        $n = UserNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'Тест',
            'body' => 'Бие',
            'url' => '/dept-dashboard',
        ]);

        $this->actingAs($user)
            ->postJson(route('notifications.read', $n))
            ->assertOk();

        $this->assertNotNull($n->fresh()->read_at);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'title' => 'Хоёр',
        ]);

        $this->actingAs($user)
            ->postJson(route('notifications.clear'))
            ->assertOk();

        $this->assertSame(0, UserNotification::query()->where('user_id', $user->id)->count());
    }
}
