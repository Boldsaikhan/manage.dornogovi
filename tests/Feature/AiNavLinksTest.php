<?php

namespace Tests\Feature;

use App\Models\AiMessage;
use App\Models\Decree;
use App\Models\User;
use App\Services\Ai\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiNavLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_decree_answer_includes_clickable_source_and_item_links(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Decree::create([
            'category' => 'zahiramj',
            'kind' => 'zahiramj_a',
            'number' => '01',
            'title' => 'Бүтээмжийн жил',
            'issued_on' => '2026-08-25',
            'person_name' => 'Б.Гантөмөр',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        $result = app(AssistantService::class)->ask(
            $admin,
            'бүртгэлт байгаа захирамжийн мэдээлэл гаргаж өг',
        );

        $this->assertNotEmpty($result['sources']);
        $this->assertSame('decrees', $result['sources'][0]['module'] ?? null);
        $this->assertNotEmpty($result['sources'][0]['href'] ?? null);
        $this->assertStringContainsString('/modules/decrees', $result['sources'][0]['href']);

        $this->assertNotEmpty($result['links']);
        $this->assertTrue(collect($result['links'])->contains(
            fn (array $link) => str_contains($link['href'], 'tab=zahiramj_a')
                && str_contains($link['label'], '01'),
        ));

        $assistant = AiMessage::query()->where('role', 'assistant')->latest('id')->first();
        $this->assertNotNull($assistant);
        $this->assertNotEmpty($assistant->meta['sources'] ?? []);
        $this->assertNotEmpty($assistant->meta['links'] ?? []);
    }

    public function test_briefing_items_carry_href(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $briefing = app(AssistantService::class)->briefing($admin);

        $this->assertNotNull($briefing);
        $this->assertNotEmpty($briefing['items']);
        foreach ($briefing['items'] as $item) {
            $this->assertNotEmpty($item['href'] ?? null);
            $this->assertNotEmpty($item['route'] ?? null);
        }
    }

    public function test_panel_messages_include_meta(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson(route('ai.panel.ask'), ['message' => 'товч мэдээлэл'])
            ->assertOk()
            ->assertJsonStructure([
                'messages' => [
                    ['id', 'role', 'content'],
                ],
            ]);

        $payload = $this->actingAs($admin)
            ->getJson(route('ai.panel'))
            ->assertOk()
            ->json();

        $assistant = collect($payload['messages'] ?? [])->firstWhere('role', 'assistant');
        $this->assertNotNull($assistant);
        $this->assertArrayHasKey('meta', $assistant);
    }
}
