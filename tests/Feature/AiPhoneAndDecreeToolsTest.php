<?php

namespace Tests\Feature;

use App\Models\Decree;
use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use App\Services\Ai\IntentRouter;
use App\Services\Ai\Tools\DocumentTools;
use App\Services\Ai\Tools\SystemTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPhoneAndDecreeToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_intent_routes_phone_questions_to_phone_directory(): void
    {
        $router = new IntentRouter;
        $route = $router->route('утасны дугаараас газрын албаны даргын дугаарыг олж өг');

        $this->assertSame('phone_directory', $route['intent']);
        $this->assertSame('search_phone_directory', $route['tools'][0]['name']);
        $q = mb_strtolower($route['tools'][0]['args']['q'] ?? '');
        $this->assertTrue(
            str_contains($q, 'газр') || str_contains($q, 'алба') || str_contains($q, 'даг'),
            "Expected org/position tokens in q, got: {$q}"
        );
    }

    public function test_intent_routes_decree_list_without_junk_query(): void
    {
        $router = new IntentRouter;
        $route = $router->route('бүртгэлт байгаа захирамжийн мэдээлэл гаргаж өг');

        $this->assertSame('orders', $route['intent']);
        $this->assertSame('search_orders', $route['tools'][0]['name']);
        $this->assertSame('', $route['tools'][0]['args']['q'] ?? '');
        $this->assertSame('zahiramj_', $route['tools'][0]['args']['kind'] ?? null);
    }

    public function test_phone_directory_tool_finds_entries(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::query()->create([
            'org_name' => 'Газрын харилцаа, геодези, зураг зүйн алба',
            'category' => 'baiguullaga',
            'person_name' => 'Б.Болд',
            'position' => 'Албаны дарга',
            'mobile_phone' => '99112233',
            'org_order' => 1,
            'sort_order' => 1,
        ]);

        $result = app(SystemTools::class)->searchPhoneDirectory($admin, ['q' => 'газар']);

        $this->assertCount(1, $result['items']);
        $this->assertSame('99112233', $result['items'][0]['mobile_phone']);
    }

    public function test_decree_search_lists_registered_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Decree::query()->create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Бүтээмжийн жил',
            'issued_on' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);

        $result = app(DocumentTools::class)->searchDecrees($admin, [
            'q' => '',
            'kind' => 'zahiramj_',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('01', $result['items'][0]['number']);
    }

    public function test_ask_returns_phone_data_from_directory(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        PhoneDirectoryEntry::query()->create([
            'org_name' => 'Газрын алба',
            'category' => 'baiguullaga',
            'person_name' => 'Д.Дорж',
            'position' => 'Албаны дарга',
            'mobile_phone' => '88001122',
            'org_order' => 1,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('ai.ask'), ['message' => 'утасны жагсаалтаас газрын албаны даргыг ол'])
            ->assertRedirect();

        $assistant = \App\Models\AiMessage::query()->where('role', 'assistant')->latest('id')->first();
        $this->assertNotNull($assistant);
        $this->assertStringContainsString('88001122', $assistant->content);
    }

    public function test_ask_returns_decree_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Decree::query()->create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '07',
            'title' => 'Туршилтын захирамж',
            'issued_on' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('ai.ask'), ['message' => 'бүртгэлт байгаа захирамжийн мэдээлэл гаргаж өг'])
            ->assertRedirect();

        $assistant = \App\Models\AiMessage::query()->where('role', 'assistant')->latest('id')->first();
        $this->assertNotNull($assistant);
        $this->assertStringNotContainsString('олдсонгүй', $assistant->content);
        $this->assertStringContainsString('07', $assistant->content);
    }
}
