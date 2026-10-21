<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\User;
use App\Services\Ai\IntentRouter;
use App\Services\Ai\Tools\DocumentTools;
use App\Support\SearchTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manage AI нь системийн бодит өгөгдлөөс тодорхой хариу өгөх ёстой.
 */
class AiDecreeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_endings_are_stripped_from_search_words(): void
    {
        /*
         * Харьяалахын нөхцөл тасарч, нэр нь үндсээрээ үлдэнэ. Нөхцөлийн
         * хэлбэр олон тул яг нэг үндэс шаардахгүй — «болдсайхан» гэсэн
         * бичлэгт таарах эхлэл байхад хангалттай.
         */
        $tokens = SearchTerms::tokens('Болдсайханы гаргасан бүх дугаарыг');

        $this->assertCount(1, $tokens);
        $this->assertStringStartsWith($tokens[0], 'болдсайхан');
        $this->assertGreaterThanOrEqual(8, mb_strlen($tokens[0]));

        // Богино үг хөндөгдөхгүй.
        $this->assertSame(['тушаал'], SearchTerms::tokens('тушаал'));

        // Зөвхөн туслах үгтэй асуултад хайх үг үлдэхгүй.
        $this->assertSame([], SearchTerms::tokens('нийт хэдэн байна'));
    }

    public function test_a_name_with_a_case_ending_finds_the_decree(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Decree::query()->create([
            'kind' => 'zahiramj_a',
            'number' => 'А/125',
            'title' => 'Ажилд томилох тухай',
            'person_name' => 'Б.Болдсайхан',
            'issued_on' => '2026-05-10',
        ]);

        Decree::query()->create([
            'kind' => 'zahiramj_a',
            'number' => 'А/126',
            'title' => 'Өөр хүний захирамж',
            'person_name' => 'Д.Отгонбаяр',
            'issued_on' => '2026-05-11',
        ]);

        $router = new IntentRouter;
        $route = $router->route('Болдсайханы гаргасан бүх захирамжийн дугаарыг гаргаж өг');

        $this->assertSame('orders', $route['intent']);

        $result = app(DocumentTools::class)->searchDecrees($admin, $route['tools'][0]['args']);

        $this->assertSame(1, $result['total']);
        $this->assertSame('А/125', $result['items'][0]['number']);
    }

    public function test_the_total_counts_every_match_not_just_the_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (range(1, 25) as $i) {
            Decree::query()->create([
                'kind' => 'tushaal_b',
                'number' => 'Б/'.$i,
                'title' => 'Шийдвэр '.$i,
                'issued_on' => '2026-03-0'.(($i % 9) + 1),
            ]);
        }

        $result = app(DocumentTools::class)->searchDecrees($admin, ['kind' => 'tushaal_']);

        // Жагсаалт 20-оор хязгаарлагдана, нийт тоо бүрэн.
        $this->assertSame(25, $result['total']);
        $this->assertCount(20, $result['items']);
    }

    public function test_a_user_without_access_gets_nothing_from_the_assistant(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        Decree::query()->create([
            'kind' => 'zahiramj_a',
            'number' => 'А/1',
            'title' => 'Нууц захирамж',
            'issued_on' => '2026-01-05',
        ]);

        $registry = app(\App\Services\Ai\Tools\ToolRegistry::class);
        $result = $registry->run('search_orders', $user, []);

        // Хандах эрхгүй бол өгөгдөл огт буцаахгүй.
        $this->assertFalse($result['ok']);
        $this->assertTrue($result['denied']);
        $this->assertArrayNotHasKey('data', $result);
    }
}
