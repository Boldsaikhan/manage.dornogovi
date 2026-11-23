<?php

namespace App\Services\Ai;

use App\Models\Meeting;
use App\Services\Ai\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Хурлын аудио → MP3 хөрвүүлэлт → Whisper STT → тэмдэглэлийн текст.
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

        $this->setProgress($meeting, Meeting::TRANSCRIPTION_CONVERTING, 5, 'Аудиог MP3 болгож хөрвүүлж байна…');

        $mp3Relative = $this->ensureMp3($meeting);

        $this->setProgress($meeting, Meeting::TRANSCRIPTION_PROCESSING, 45, 'Текст рүү хөрвүүлж байна (Whisper)…');

        $absolute = Storage::disk('local')->path($mp3Relative);
        $raw = $this->whisper($absolute, basename($mp3Relative));

        $this->setProgress($meeting, Meeting::TRANSCRIPTION_PROCESSING, 80, 'Тэмдэглэл цэгцэлж байна…');

        $minutes = $this->formatMinutes($raw, $meeting);

        $meeting->forceFill([
            'transcript' => $raw,
            'minutes' => $minutes,
            'transcription_status' => Meeting::TRANSCRIPTION_READY,
            'transcription_progress' => 100,
            'transcription_stage' => 'Бэлэн',
            'transcription_error' => null,
            'transcribed_at' => now(),
        ])->save();

        return $meeting->refresh();
    }

    public function markFailed(Meeting $meeting, string $message): void
    {
        $meeting->forceFill([
            'transcription_status' => Meeting::TRANSCRIPTION_FAILED,
            'transcription_progress' => 0,
            'transcription_stage' => 'Алдаа',
            'transcription_error' => mb_substr($message, 0, 2000),
        ])->save();
    }

    /**
     * Шаардлагатай бол ffmpeg-ээр MP3 руу хөрвүүлж, storage-д хадгална.
     */
    private function ensureMp3(Meeting $meeting): string
    {
        $relative = (string) $meeting->audio_path;
        $absolute = Storage::disk('local')->path($relative);
        $originalName = $meeting->audio_original_name ?: basename($relative);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: pathinfo($absolute, PATHINFO_EXTENSION));

        $whisperNative = ['flac', 'm4a', 'mp3', 'mp4', 'mpeg', 'mpga', 'oga', 'ogg', 'wav', 'webm'];

        if ($ext === 'mp3') {
            $this->setProgress($meeting, Meeting::TRANSCRIPTION_CONVERTING, 35, 'MP3 файл бэлэн');

            return $relative;
        }

        $ffmpeg = $this->findFfmpeg();
        if ($ffmpeg === null) {
            // Whisper шууд дэмждэг формат бол ffmpegгүйгээр үргэлжлүүлнэ.
            if (in_array($ext, $whisperNative, true)) {
                $this->setProgress($meeting, Meeting::TRANSCRIPTION_CONVERTING, 35, 'Хөрвүүлэлт алгасна (ffmpeg байхгүй)');

                return $relative;
            }

            throw new RuntimeException(
                'Аудиог MP3 болгохын тулд сервер дээр ffmpeg суусан байх ёстой. '
                .'Админд хандана уу, эсвэл MP3 форматаар дахин оруулна уу.'
            );
        }

        $this->setProgress($meeting, Meeting::TRANSCRIPTION_CONVERTING, 12, 'MP3 руу хөрвүүлж байна…');

        $newRelative = 'meeting-audio/'.$meeting->id.'/converted-'.uniqid('', true).'.mp3';
        Storage::disk('local')->makeDirectory(dirname($newRelative));
        $newAbsolute = Storage::disk('local')->path($newRelative);

        $duration = $this->probeDurationSeconds($ffmpeg, $absolute);
        $this->runFfmpegToMp3($ffmpeg, $absolute, $newAbsolute, $meeting, $duration);

        if (! is_file($newAbsolute) || filesize($newAbsolute) < 1) {
            throw new RuntimeException('MP3 хөрвүүлэлт амжилтгүй боллоо.');
        }

        $oldRelative = $relative;
        $meeting->forceFill([
            'audio_path' => $newRelative,
            'audio_original_name' => pathinfo($originalName, PATHINFO_FILENAME).'.mp3',
            'audio_mime' => 'audio/mpeg',
            'audio_size' => filesize($newAbsolute),
        ])->save();

        if ($oldRelative !== $newRelative && Storage::disk('local')->exists($oldRelative)) {
            Storage::disk('local')->delete($oldRelative);
        }

        $this->setProgress($meeting, Meeting::TRANSCRIPTION_CONVERTING, 40, 'MP3 хөрвүүлэлт дууслаа');

        return $newRelative;
    }

    private function runFfmpegToMp3(
        string $ffmpeg,
        string $input,
        string $output,
        Meeting $meeting,
        ?float $durationSeconds,
    ): void {
        $cmd = [
            $ffmpeg,
            '-y',
            '-i', $input,
            '-vn',
            '-acodec', 'libmp3lame',
            '-ar', '16000',
            '-ac', '1',
            '-b:a', '64k',
            '-progress', 'pipe:1',
            '-nostats',
            $output,
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptor, $pipes, null, null, ['bypass_shell' => true]);

        if (! is_resource($process)) {
            throw new RuntimeException('MP3 хөрвүүлэлт эхлүүлж чадсангүй (ffmpeg).');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stderr = '';
        $lastOutMs = 0;
        $lastPersist = 0.0;

        while (true) {
            $status = proc_get_status($process);
            $chunkOut = stream_get_contents($pipes[1]) ?: '';
            $chunkErr = stream_get_contents($pipes[2]) ?: '';
            $stderr .= $chunkErr;

            if ($chunkOut !== '') {
                foreach (explode("\n", $chunkOut) as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'out_time_ms=')) {
                        $lastOutMs = (int) substr($line, strlen('out_time_ms='));
                    }
                }

                if ($durationSeconds && $durationSeconds > 0 && $lastOutMs > 0) {
                    $ratio = min(1, ($lastOutMs / 1_000_000) / $durationSeconds);
                    $percent = (int) round(12 + ($ratio * 26)); // 12 → 38
                    $now = microtime(true);
                    if ($now - $lastPersist >= 0.4) {
                        $this->setProgress(
                            $meeting,
                            Meeting::TRANSCRIPTION_CONVERTING,
                            $percent,
                            'MP3 руу хөрвүүлж байна… '.$percent.'%',
                        );
                        $lastPersist = $now;
                    }
                }
            }

            if (! $status['running']) {
                break;
            }

            usleep(150_000);
        }

        $stderr .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        if ($code !== 0) {
            if (is_file($output)) {
                @unlink($output);
            }

            throw new RuntimeException(
                'Аудиог MP3 болгож чадсангүй. '
                .mb_substr(trim($stderr), 0, 400)
            );
        }
    }

    private function probeDurationSeconds(string $ffmpeg, string $absolutePath): ?float
    {
        $ffprobe = $this->findFfprobe($ffmpeg);
        if ($ffprobe === null) {
            return null;
        }

        $cmd = [
            $ffprobe,
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $absolutePath,
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptor, $pipes, null, null, ['bypass_shell' => true]);
        if (! is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        $out = trim((string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if ($out === '' || ! is_numeric($out)) {
            return null;
        }

        $seconds = (float) $out;

        return $seconds > 0 ? $seconds : null;
    }

    private function findFfprobe(string $ffmpeg): ?string
    {
        $dir = dirname($ffmpeg);
        $candidate = $dir.DIRECTORY_SEPARATOR.'ffprobe'.(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');
        if (is_file($candidate)) {
            return $candidate;
        }

        $which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'which';
        $out = [];
        $code = 0;
        @exec($which.' ffprobe', $out, $code);
        if ($code === 0 && ! empty($out[0]) && is_file(trim($out[0]))) {
            return trim($out[0]);
        }

        foreach (['/usr/bin/ffprobe', '/usr/local/bin/ffprobe'] as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function findFfmpeg(): ?string
    {
        $configured = trim((string) config('ai.ffmpeg_path', ''));
        if ($configured !== '' && is_file($configured)) {
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

    private function setProgress(Meeting $meeting, string $status, int $percent, string $stage): void
    {
        $meeting->forceFill([
            'transcription_status' => $status,
            'transcription_progress' => max(0, min(100, $percent)),
            'transcription_stage' => mb_substr($stage, 0, 64),
            'transcription_error' => null,
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

        // Whisper API 'mn' (монгол) language кодыг дэмждэггүй — автоматаар илрүүлнэ.
        $response = Http::baseUrl(rtrim((string) config('ai.openai_base_url'), '/'))
            ->withToken($apiKey)
            ->timeout($timeout)
            ->attach('file', file_get_contents($absolutePath), $filename)
            ->post('/audio/transcriptions', [
                'model' => $model,
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
