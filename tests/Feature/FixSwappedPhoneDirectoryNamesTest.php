<?php

namespace Tests\Feature;

use App\Models\PhoneDirectoryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixSwappedPhoneDirectoryNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_swaps_a_title_and_dot_initial_name_back(): void
    {
        $row = PhoneDirectoryEntry::create([
            'org_name' => 'УЛААНБАДРАХ СУМ',
            'category' => 'sum',
            'person_name' => 'Засаг даргын орлогч',
            'position' => 'М.Мөнх-Эрдэнэ',
        ]);

        $this->artisan('phone-directory:fix-swapped-names')->assertSuccessful();

        $row->refresh();
        $this->assertSame('М.Мөнх-Эрдэнэ', $row->person_name);
        $this->assertSame('Засаг даргын орлогч', $row->position);
    }

    public function test_it_swaps_a_title_and_two_word_name_back(): void
    {
        $row = PhoneDirectoryEntry::create([
            'org_name' => 'АЙРАГ СУМ',
            'category' => 'sum',
            'person_name' => '1-р баг, Нарт багийн Засаг дарга',
            'position' => 'Буурал Байгальсайхан',
        ]);

        $this->artisan('phone-directory:fix-swapped-names')->assertSuccessful();

        $row->refresh();
        $this->assertSame('Буурал Байгальсайхан', $row->person_name);
        $this->assertSame('1-р баг, Нарт багийн Засаг дарга', $row->position);
    }

    public function test_it_leaves_correctly_ordered_rows_untouched(): void
    {
        $row = PhoneDirectoryEntry::create([
            'org_name' => 'ИХХЭТ СУМ',
            'category' => 'sum',
            'person_name' => 'Б.Болдхуяг',
            'position' => 'Засаг дарга',
        ]);

        $this->artisan('phone-directory:fix-swapped-names')->assertSuccessful();

        $row->refresh();
        $this->assertSame('Б.Болдхуяг', $row->person_name);
        $this->assertSame('Засаг дарга', $row->position);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $row = PhoneDirectoryEntry::create([
            'org_name' => 'УЛААНБАДРАХ СУМ',
            'category' => 'sum',
            'person_name' => 'Засаг даргын орлогч',
            'position' => 'М.Мөнх-Эрдэнэ',
        ]);

        $this->artisan('phone-directory:fix-swapped-names', ['--dry-run' => true])->assertSuccessful();

        $row->refresh();
        $this->assertSame('Засаг даргын орлогч', $row->person_name);
        $this->assertSame('М.Мөнх-Эрдэнэ', $row->position);
    }
}
