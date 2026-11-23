<?php

namespace App\Services\Ai;

use App\Models\Meeting;
use App\Services\Ai\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Хурлын аудио → Whisper STT → тэмдэглэлийн текст.
 *
 * OpenAI API түлхүүрийг AiSettings (Системийн тохиргоо → Manage AI)-аас авна.
 */
class MeetingTranscriptionService
{
    public function __construct(
        private AiSettings $settings,
        private OpenAiProvider $openAi,
    ) {}

    public function isReady(): bool
    {
        return $this->settings->hasOpenAiKey();
    }

    public function configHint(): string
    {
        return 'Системийн тохиргоо → Manage AI хэсэгт OpenAI API түлхүүр оруулна уу. Whisper (speech-to-text) энэ түлхүүрээр ажиллана.';
    }

    public function transcribeMeeting(Meeting $meeting): Meeting
    {
        if (! $this->isReady()) {
            throw new RuntimeException($this->configHint());
        }

        if (! $meeting->audio_path || ! Storage::disk('local')->exists($meeting->audio_path)) {
            throw new RuntimeException('Аудио файл олдсонгүй.');
        }

        $meeting->forceFill([
            'transcription_status' => Meeting::TRANSCRIPTION_PROCESSING,
            'transcription_error' => null,
        ])->save();

        $absolute = Storage::disk('local')->path($meeting->audio_path);
        $raw = $this->whisper($absolute, $meeting->audio_original_name ?: basename($meeting->audio_path));

        $minutes = $this->formatMinutes($raw, $meeting);

        $meeting->forceFill([
            'transcript' => $raw,
            'minutes' => $minutes,
            'transcription_status' => Meeting::TRANSCRIPTION_READY,
            'transcription_error' => null,
            'transcribed_at' => now(),
        ])->save();

        return $meeting->refresh();
    }

    public function markFailed(Meeting $meeting, string $message): void
    {
        $meeting->forceFill([
            'transcription_status' => Meeting::TRANSCRIPTION_FAILED,
            'transcription_error' => mb_substr($message, 0, 2000),
        ])->save();
    }

    private function whisper(string $absolutePath, string $filename): string
    {
        $apiKey = $this->settings->openaiApiKey();
        if (! $apiKey) {
            throw new RuntimeException($this->configHint());
        }

        $timeout = (int) config('ai.whisper_timeout', 180);
        $model = (string) config('ai.whisper_model', 'whisper-1');

        $response = Http::baseUrl(rtrim((string) config('ai.openai_base_url'), '/'))
            ->withToken($apiKey)
            ->timeout($timeout)
            ->attach('file', file_get_contents($absolutePath), $filename)
            ->post('/audio/transcriptions', [
                'model' => $model,
                'language' => 'mn',
                'response_format' => 'text',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Whisper алдаа: '.$response->status().' '.mb_substr($response->body(), 0, 500));
        }

        $text = trim($response->body());
        if ($text === '') {
            throw new RuntimeException('Whisper хоосон текст буцаалаа.');
        }

        return $text;
    }

    private function formatMinutes(string $transcript, Meeting $meeting): string
    {
        if (! $this->openAi->isAvailable()) {
            return $transcript;
        }

        $title = $meeting->title ?: 'Хурлын тэмдэглэл';
        $when = optional($meeting->held_at)?->format('Y-m-d H:i') ?: 'огноо тодорхойгүй';

        try {
            $result = $this->openAi->chat([
                [
                    'role' => 'system',
                    'content' => 'Та Монгол хэлээр албан хурлын тэмдэглэл бичдэг туслах. '
                        .'Өгөгдсөн ярианы транскриптаас цэгцтэй тэмдэглэл гарга. '
                        .'Бүтэц: 1) Гарчиг/сэдэв 2) Огноо 3) Оролцогчид (хэрэв дурдсан бол) '
                        .'4) Хэлэлцсэн асуудал 5) Шийдвэр/үүрэг. '
                        .'Зөвхөн тэмдэглэлийн текстийг буцаа. Англиар бичихгүй.',
                ],
                [
                    'role' => 'user',
                    'content' => "Гарчиг: {$title}\nОгноо: {$when}\n\nТранскрипт:\n{$transcript}",
                ],
            ], [
                'temperature' => 0.2,
            ]);

            $content = trim((string) ($result['content'] ?? ''));

            return $content !== '' ? $content : $transcript;
        } catch (\Throwable) {
            return $transcript;
        }
    }
}
