<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserModulePermission;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecreeNewRowNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(string $moduleKey = 'decrees:zahiramj_a'): User
    {
        $viewer = User::factory()->create(['is_admin' => false]);

        UserModulePermission::create([
            'user_id' => $viewer->id,
            'module_key' => $moduleKey,
            'level' => 'view',
        ]);

        return $viewer;
    }

    public function test_adding_a_row_notifies_other_viewers_but_not_the_actor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $viewer = $this->viewer();

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'zahiramj_a',
        ])->assertRedirect();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $viewer->id,
            'title' => 'Захирамж, тушаал',
        ]);

        $this->assertSame(0, UserNotification::query()->where('user_id', $admin->id)->count());
    }

    public function test_a_blank_row_also_notifies_viewers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $viewer = $this->viewer('decrees:blank');

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'blank',
        ])->assertRedirect();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $viewer->id,
            'title' => 'Захирамж, тушаал',
        ]);
    }

    public function test_a_user_without_view_access_is_not_notified(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $stranger = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)->post(route('decrees.store'), [
            'tab' => 'zahiramj_a',
        ])->assertRedirect();

        $this->assertSame(0, UserNotification::query()->where('user_id', $stranger->id)->count());
    }

    public function test_importing_rows_notifies_viewers_once(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $viewer = $this->viewer();

        $this->actingAs($admin)
            ->post(route('decrees.import.store'), [
                'tab' => 'zahiramj_a',
                'entries' => [
                    ['number' => '01', 'issued_on' => '2026-01-02', 'title' => 'Нэгдүгээр'],
                    ['number' => '02', 'issued_on' => '2026-01-09', 'title' => 'Хоёрдугаар'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, UserNotification::query()->where('user_id', $viewer->id)->count());
    }
}
