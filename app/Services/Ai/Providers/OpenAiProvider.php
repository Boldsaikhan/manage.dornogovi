<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AiProvider
{
    public function __construct(private AiSettings $settings) {}

    public function name(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return $this->settings->hasOpenAiKey();
    }

    public function chat(array $messages, array $options = []): array
    {
        $apiKey = $this->settings->openaiApiKey();
        if (! $apiKey) {
            throw new RuntimeException('OpenAI API түлхүүр тохируулаагүй байна.');
        }

        $response = Http::baseUrl(rtrim((string) config('ai.openai_base_url'), '/'))
            ->withToken($apiKey)
            ->timeout((int) config('ai.openai_timeout', 45))
            ->acceptJson()
            ->post('/chat/completions', [
                'model' => $options['model'] ?? $this->settings->openaiModel(),
                'temperature' => $options['temperature'] ?? 0.2,
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI алдаа: '.$response->status().' '.$response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI хоосон хариу буцаалаа.');
        }

        return [
            'content' => trim($content),
            'provider' => $this->name(),
        ];
    }
}
