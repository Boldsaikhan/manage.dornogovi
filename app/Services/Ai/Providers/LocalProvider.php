<?php

namespace App\Services\Ai\Providers;

class LocalProvider implements AiProvider
{
    public function name(): string
    {
        return 'local';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function chat(array $messages, array $options = []): array
    {
        $toolContext = (string) ($options['tool_context'] ?? '');
        if (trim($toolContext) !== '') {
            return [
                'content' => $toolContext,
                'provider' => $this->name(),
            ];
        }

        return [
            'content' => "Системийн мэдээллийн сангаас баталгаатай мэдээлэл олдсонгүй.\n\nЖишээ: «хүлээгдэж буй чөлөө», «үүрэг даалгавар», «захирамж», «төлөвлөгөө», «өнөөдрийн товч».",
            'provider' => $this->name(),
        ];
    }
}
