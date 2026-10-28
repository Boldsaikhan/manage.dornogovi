<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «Хариуцах эзэн» сонгоход хүсэлт хүнд болж 500 алдаа өгч байсан.
 *
 * Шалтгаан нь мэдэгдэл илгээхийн тулд хэрэглэгч бүрийн төлөө утасны
 * бүтэн жагсаалтыг дахин уншдаг байсан явдал.
 */
class TaskResponsiblePickTest extends TestCase
{
    use RefreshDatabase;

    private function task(): Task
    {
        $source = TaskSource::where('key', 'governor')->firstOrFail();

        return Task::create([
            'task_source_id' => $source->id,
            'text' => 'Туршилтын үүрэг',
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_set_a_responsible_person(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $task = $this->task();

        $this->actingAs($admin)
            ->patch(route('tasks.update', $task), ['responsible' => 'Б.Болд'])
            ->assertRedirect();

        $this->assertSame('Б.Болд', $task->fresh()->responsible);
    }

    public function test_the_phone_directory_is_not_reread_for_every_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $task = $this->task();

        for ($i = 0; $i < 25; $i++) {
            User::factory()->create(['phone' => '9900'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)]);
        }

        for ($i = 0; $i < 25; $i++) {
            PhoneDirectoryEntry::create([
                'org_name' => 'Тест алба',
                'person_name' => 'Болд Сайхан '.$i,
                'mobile_phone' => '9900'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'sort_order' => $i,
                'org_order' => 1,
            ]);
        }

        $directoryReads = 0;
        DB::listen(function ($query) use (&$directoryReads): void {
            if (str_contains($query->sql, 'phone_directory_entries')) {
                $directoryReads++;
            }
        });

        $this->actingAs($admin)
            ->patch(route('tasks.update', $task), ['responsible' => 'Б.Болд'])
            ->assertRedirect();

        // Урьд нь хэрэглэгч бүрийн төлөө бүтэн жагсаалтыг уншдаг байв.
        $this->assertLessThanOrEqual(
            3,
            $directoryReads,
            "Утасны жагсаалт {$directoryReads} удаа уншигдлаа.",
        );
    }
}
