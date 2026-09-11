<?php

namespace Tests\Feature;

use App\Models\TaskSource;
use App\Models\User;
use App\Services\Push\EmployeePushNotifier;
use App\Services\Push\WebPushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Мэдэгдэл илгээх үед алдаа гарсан ч өгөгдөл хадгалагдана.
 *
 * Push нь гадаад серверүүд рүү сүлжээгээр ханддаг тул хааяа унадаг.
 */
class PushFailureDoesNotBreakSavingTest extends TestCase
{
    use RefreshDatabase;

    private function breakPush(): void
    {
        $this->app->bind(WebPushNotifier::class, function () {
            return new class extends WebPushNotifier
            {
                public function __construct() {}

                public function sendToUsers(iterable $users, array $payload): void
                {
                    throw new RuntimeException('Сүлжээ саатлаа.');
                }
            };
        });
    }

    public function test_adding_a_task_still_works_when_push_fails(): void
    {
        $this->breakPush();

        $admin = User::factory()->create(['is_admin' => true]);
        $source = TaskSource::query()->firstOrCreate(
            ['key' => 'directive'],
            ['name' => 'Үүрэг чиглэл', 'layout' => 'directive'],
        );

        $this->actingAs($admin)
            ->from(route('tasks.index'))
            ->post(route('tasks.store'), ['kind' => 'directive', 'text' => 'Шинэ үүрэг'])
            ->assertRedirect();

        $this->assertSame(1, $source->tasks()->count());
    }

    public function test_the_notifier_swallows_the_error(): void
    {
        $this->breakPush();

        $user = User::factory()->create();

        // Алдаа цааш дамжихгүй — in-app мэдэгдэл нь үлдэнэ.
        app(EmployeePushNotifier::class)->notifyUsers([$user->id], [
            'title' => 'Туршилт',
            'tag' => 'test',
        ]);

        $this->assertSame(1, $user->notifications()->count());
    }
}
