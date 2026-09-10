<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Decree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Захирамж, тушаалын өөрчлөлтийн лог.
 *
 * «Энэ мөр хаанаас гарч ирэв?» гэдэгт хариулах чадвартай эсэх.
 */
class DecreeAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => 'Б.Болдсайхан']);
    }

    public function test_adding_a_row_by_hand_is_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('decrees.store'), ['tab' => 'zahiramj_a', 'title' => ''])
            ->assertRedirect();

        $log = AuditLog::query()->where('model_type', 'decree')->firstOrFail();

        $this->assertSame('created', $log->action);
        $this->assertSame('decrees:zahiramj_a', $log->scope);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringContainsString('Шинэ мөр', (string) $log->summary);
    }

    public function test_importing_from_a_file_is_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('decrees.import.store'), [
                'tab' => 'zahiramj_a',
                'entries' => [
                    ['number' => '01', 'title' => 'Нэг'],
                    ['number' => '02', 'title' => 'Хоёр'],
                ],
            ])
            ->assertRedirect();

        $log = AuditLog::query()->where('action', 'imported')->firstOrFail();

        $this->assertSame('decrees:zahiramj_a', $log->scope);
        $this->assertStringContainsString('2 мөр нэмэгдэж', (string) $log->summary);
        $this->assertSame(2, $log->changes['created'] ?? null);
    }

    public function test_editing_and_deleting_are_logged(): void
    {
        $admin = $this->admin();

        $decree = Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '05',
            'title' => 'Хуучин гарчиг',
        ]);

        $this->actingAs($admin)
            ->patch(route('decrees.update', $decree), ['title' => 'Шинэ гарчиг'])
            ->assertRedirect();

        $updated = AuditLog::query()->where('action', 'updated')->firstOrFail();
        $this->assertSame($decree->id, $updated->model_id);
        $this->assertSame('Хуучин гарчиг', $updated->changes['title']['from'] ?? null);
        $this->assertSame('Шинэ гарчиг', $updated->changes['title']['to'] ?? null);

        $this->actingAs($admin)
            ->delete(route('decrees.destroy', $decree))
            ->assertRedirect();

        $this->assertTrue(
            AuditLog::query()->where('action', 'deleted')->where('model_id', $decree->id)->exists(),
        );
    }

    public function test_the_log_can_be_read_for_the_active_tab(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('decrees.store'), ['tab' => 'zahiramj_a', 'title' => '']);
        $this->actingAs($admin)->post(route('decrees.store'), ['tab' => 'tushaal_a', 'title' => '']);

        $rows = $this->actingAs($admin)
            ->get(route('decrees.logs', ['tab' => 'zahiramj_a']))
            ->assertOk()
            ->json('rows');

        $this->assertCount(1, $rows);
        $this->assertSame('Шинээр нэмсэн', $rows[0]['action_label']);
        $this->assertSame('Б.Болдсайхан', $rows[0]['user']);
    }

    public function test_a_user_without_access_cannot_read_the_log(): void
    {
        $user = User::factory()->create();

        \App\Models\UserModulePermission::query()->where('user_id', $user->id)->delete();

        $this->actingAs($user)
            ->get(route('decrees.logs', ['tab' => 'zahiramj_a']))
            ->assertForbidden();
    }
}
