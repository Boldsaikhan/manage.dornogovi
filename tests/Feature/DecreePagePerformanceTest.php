<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use App\Models\UserModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Захирамж, тушаалын хуудас хэт олон асуулга үүсгэхгүй байх.
 *
 * Эрх шалгах бүрд өгөгдлийн сан руу давтаж ханддаг байсныг барих зорилготой.
 */
class DecreePagePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_does_not_run_a_query_per_permission_check(): void
    {
        $user = User::factory()->create(['is_specialist' => true]);

        UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'decrees',
            'level' => 'edit',
        ]);

        for ($i = 1; $i <= 120; $i++) {
            Decree::create([
                'category' => 'zahiramj',
                'kind' => 'zahiramj_a',
                'number' => str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'title' => 'Мөр '.$i,
            ]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->actingAs($user->fresh())
            ->get(route('decrees.index', ['tab' => 'zahiramj_a']))
            ->assertOk();

        // Хэмжилт: засварын өмнө 84 асуулга байсан.
        $this->assertLessThan(
            60,
            $queries,
            "Хуудас {$queries} асуулга үүсгэлээ — эрхийн шалгалт дахин сан руу ханддаг болсон байж магадгүй.",
        );
    }
}
