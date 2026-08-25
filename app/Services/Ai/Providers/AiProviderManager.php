<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiSettings;

class AiProviderManager
{
    public function __construct(
        private AiSettings $settings,
        private OpenAiProvider $openAi,
        private LocalProvider $local,
    ) {}

    public function resolve(): AiProvider
    {
        if ($this->settings->provider() === 'openai' && $this->openAi->isAvailable()) {
            return $this->openAi;
        }

        return $this->local;
    }
}
