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
        $originalName = $meeting->audio_original_name ?: basename($meeting->audio_path);
        [$whisperPath, $whisperName, $tempPath] = $this->prepareForWhisper($absolute, $originalName);

        try {
            $raw = $this->whisper($whisperPath, $whisperName);
        } finally {
            if ($tempPath && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

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

    /**
     * Whisper шууд дэмждэггүй өргөтгөл (ж: WMA)-ыг ffmpeg-ээр mp3 руу хөрвүүлнэ.
     *
     * @return array{0: string, 1: string, 2: ?string} [path, filename, tempPathToCleanup]
     */
    private function prepareForWhisper(string $absolutePath, string $filename): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: pathinfo($absolutePath, PATHINFO_EXTENSION));
        $native = ['flac', 'm4a', 'mp3', 'mp4', 'mpeg', 'mpga', 'oga', 'ogg', 'wav', 'webm'];

        if ($ext === '' || in_array($ext, $native, true)) {
            return [$absolutePath, $filename, null];
        }

        if (! in_array($ext, ['wma', 'asf'], true)) {
            return [$absolutePath, $filename, null];
        }

        $ffmpeg = $this->findFfmpeg();
        if ($ffmpeg === null) {
            throw new RuntimeException(
                'WMA файл оруулахын тулд сервер дээр ffmpeg суусан байх ёстой. '
                .'Админд хандана уу, эсвэл MP3/WAV/M4A болгож дахин оруулна уу.'
            );
        }

        $tempPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'meeting-wma-'.uniqid('', true).'.mp3';

        $cmd = [
            $ffmpeg,
            '-y',
            '-i', $absolutePath,
            '-vn',
            '-acodec', 'libmp3lame',
            '-ar', '16000',
            '-ac', '1',
            $tempPath,
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptor, $pipes, null, null, ['bypass_shell' => true]);

        if (! is_resource($process)) {
            throw new RuntimeException('WMA → MP3 хөрвүүлэлт эхлүүлж чадсангүй (ffmpeg).');
        }

        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        if ($code !== 0 || ! is_file($tempPath) || filesize($tempPath) < 1) {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }

            throw new RuntimeException(
                'WMA файлыг хөрвүүлж чадсангүй. '
                .mb_substr(trim($stderr), 0, 400)
            );
        }

        return [$tempPath, basename($tempPath), $tempPath];
    }

    private function findFfmpeg(): ?string
    {
        $configured = trim((string) config('ai.ffmpeg_path', ''));
        if ($configured !== '' && is_file($configured) && is_executable($configured)) {
            return $configured;
        }

        $which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'which';
        $out = [];
        $code = 0;
        @exec($which.' ffmpeg', $out, $code);

        if ($code === 0 && ! empty($out[0]) && is_file(trim($out[0]))) {
            return trim($out[0]);
        }

        foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'] as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
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
