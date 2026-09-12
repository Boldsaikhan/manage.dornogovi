<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MongolianCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Системийн тохиргоо — харьяалахын тийн ялгалын үгс.
 */
class GenitiveSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_word_list_is_shown(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.systems.index'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $words = collect($page->toArray()['props']['genitiveWords']);

                $row = $words->firstWhere('word', 'хэлтэс');

                $this->assertSame('хэлтсийн', $row['form']);
                // Үндсэн үгийг устгахгүй.
                $this->assertTrue($row['is_default']);
            });
    }

    public function test_a_new_word_can_be_added_and_is_used(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Дүрмээр буруу гардаг үг.
        $before = MongolianCase::genitiveWord('чуулган');

        $this->actingAs($admin)
            ->from(route('admin.systems.index'))
            ->patch(route('admin.genitive.update'), [
                'words' => [
                    ['word' => 'чуулган', 'form' => 'чуулганы'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        MongolianCase::forget();

        $this->assertSame('чуулганы', MongolianCase::genitiveWord('чуулган'));
        $this->assertNotSame($before, MongolianCase::genitiveWord('чуулган'));

        // Үндсэн жагсаалт нь хэвээр.
        $this->assertSame('хэлтсийн', MongolianCase::genitiveWord('хэлтэс'));
    }

    public function test_a_default_word_can_be_corrected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->from(route('admin.systems.index'))
            ->patch(route('admin.genitive.update'), [
                'words' => [
                    ['word' => 'хэлтэс', 'form' => 'хэлтэсийн'],
                ],
            ]);

        MongolianCase::forget();

        $this->assertSame('хэлтэсийн', MongolianCase::genitiveWord('хэлтэс'));
    }

    public function test_the_rule_can_be_tried_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson(route('admin.genitive.test'), [
                'word' => 'Төрийн захиргааны удирдлагын хэлтэс',
            ])
            ->assertOk()
            ->assertJson(['genitive' => 'Төрийн захиргааны удирдлагын хэлтсийн']);
    }

    public function test_a_non_admin_cannot_change_the_list(): void
    {
        $staff = User::factory()->create(['is_admin' => false]);

        $this->actingAs($staff)
            ->patch(route('admin.genitive.update'), ['words' => []])
            ->assertForbidden();
    }
}
