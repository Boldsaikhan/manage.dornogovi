<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Support\TushaalARegister2026;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Дутуу мөрийг нөхөх — байгаа мөрийг хөндөхгүй.
 */
class TushaalAFillMissingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_only_the_missing_numbers(): void
    {
        // Гараар оруулсан мөр — агуулгыг нь хөндөх ёсгүй.
        Decree::create([
            'category' => 'tushaal',
            'kind' => 'tushaal_a',
            'number' => '74',
            'title' => 'Гараар оруулсан гарчиг',
        ]);

        $added = TushaalARegister2026::fillMissing();

        $this->assertCount(73, $added);
        $this->assertNotContains('74', $added);
        $this->assertContains('71', $added);
        $this->assertSame(74, Decree::query()->where('kind', 'tushaal_a')->count());

        $this->assertSame(
            'Гараар оруулсан гарчиг',
            Decree::query()->where('kind', 'tushaal_a')->where('number', '74')->value('title'),
        );

        $a71 = Decree::query()->where('kind', 'tushaal_a')->where('number', '71')->first();
        $this->assertSame('Зардал гаргах тухай', $a71->title);
        $this->assertSame('2026-09-03', $a71->issued_on->format('Y-m-d'));
        $this->assertStringContainsString('Иргэдийн уулзалтын өрөө', (string) $a71->attachment_name);
    }

    public function test_running_it_twice_adds_nothing(): void
    {
        TushaalARegister2026::fillMissing();
        $this->assertSame([], TushaalARegister2026::fillMissing());
        $this->assertSame(74, Decree::query()->where('kind', 'tushaal_a')->count());
    }

    public function test_a_number_stored_without_padding_is_recognised(): void
    {
        // «71» гэж хадгалагдсан ч, «071»/«71» ялгаагүй танина.
        Decree::create([
            'category' => 'tushaal',
            'kind' => 'tushaal_a',
            'number' => '71',
            'title' => 'Аль хэдийн байгаа',
        ]);

        $added = TushaalARegister2026::fillMissing();

        $this->assertNotContains('71', $added);
        $this->assertSame(
            'Аль хэдийн байгаа',
            Decree::query()->where('kind', 'tushaal_a')->where('number', '71')->value('title'),
        );
    }

    public function test_the_console_command_reports_what_it_added(): void
    {
        $this->artisan('decrees:fill tushaal-a')
            ->expectsOutputToContain('А/71')
            ->assertSuccessful();

        $this->artisan('decrees:fill tushaal-a')
            ->expectsOutputToContain('дутуу мөр алга')
            ->assertSuccessful();
    }
}
