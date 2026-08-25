<?php

namespace App\Services\Ai\Providers;

interface AiProvider
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, provider: string}
     */
    public function chat(array $messages, array $options = []): array;

    public function name(): string;

    public function isAvailable(): bool;
}
