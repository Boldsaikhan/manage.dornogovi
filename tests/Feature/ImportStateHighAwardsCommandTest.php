<?php

namespace Tests\Feature;

use App\Models\Award;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportStateHighAwardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_replace_year_deletes_old_and_imports_json(): void
    {
        Award::query()->create([
            'category' => 'state_high',
            'year' => 2024,
            'surname' => 'Хуучин',
            'given_name' => 'Мөр',
            'nominated_award' => 'Хуучин шагнал',
        ]);

        Award::query()->create([
            'category' => 'state_high',
            'year' => 2025,
            'surname' => 'Үлдэх',
            'given_name' => 'Мөр',
            'nominated_award' => 'Бусад',
        ]);

        $path = storage_path('app/test-awards-2024.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'category' => 'state_high',
            'year' => 2024,
            'award_types' => ['Алтан гадас одон'],
            'entries' => [
                [
                    'year' => 2024,
                    'nominated_award' => 'АГО',
                    'surname' => 'Шинэ',
                    'given_name' => 'Оруулсан',
                    'register_no' => 'УА90010111',
                ],
                [
                    'year' => 2025,
                    'nominated_award' => 'ХХМ',
                    'surname' => 'БурууОн',
                    'given_name' => 'Алгас',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $this->artisan('awards:import-state-high', [
            '--file' => $path,
            '--year' => 2024,
            '--replace-year' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('awards', [
            'year' => 2024,
            'surname' => 'Хуучин',
        ]);

        $this->assertDatabaseHas('awards', [
            'category' => 'state_high',
            'year' => 2024,
            'surname' => 'Шинэ',
            'given_name' => 'Оруулсан',
            'nominated_award' => 'Алтан гадас одон',
        ]);

        $this->assertDatabaseMissing('awards', [
            'surname' => 'БурууОн',
        ]);

        $this->assertDatabaseHas('awards', [
            'year' => 2025,
            'surname' => 'Үлдэх',
        ]);

        $this->assertSame(1, Award::query()->where('year', 2024)->count());

        File::delete($path);
    }
}
