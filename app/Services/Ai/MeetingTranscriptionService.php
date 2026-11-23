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

    /** Нэг хэсгийн урт (сек) — эхнээсээ текст бичигдэхийн тулд жижиг хэсэгчилж Whisper дуудна. */
    private const CHUNK_SECONDS = 30;

    public function transcribeMeeting(Meeting $meeting): Meeting
    {
        if (! $this->isReady()) {
            throw new RuntimeException($this->configHint());
        }

        if (! $meeting->audio_path || ! Storage::disk('local')->exists($meeting->audio_path)) {
            throw new RuntimeException('Аудио файл олдсонгүй.');
        }

        $this->setProgress($meeting, Meeting::TRANSCRIPTION_CONVERTING, 5, 'Аудиог MP3 болгож хөрвүүлж байна…', 0);

        $mp3Relative = $this->ensureMp3($meeting);
        $absolute = Storage::disk('local')->path($mp3Relative);
        $ffmpeg = $this->findFfmpeg();
        $duration = $ffmpeg ? $this->probeDurationSeconds($ffmpeg, $absolute) : null;

        $meeting->forceFill([
            'audio_duration_sec' => $duration ? (int) round($duration) : null,
            'transcription_position_sec' => 0,
            'transcript' => '',
            'transcript_segments' => [],
        ])->save();

        $raw = $this->transcribeProgressively($meeting, $absolute, $ffmpeg, $duration);
        $meeting->refresh();

        $this->setProgress(
            $meeting,
            Meeting::TRANSCRIPTION_PROCESSING,
            92,
            'Тэмдэглэл цэгцэлж байна…',
            (int) ($meeting->audio_duration_sec ?? $meeting->transcription_position_sec ?? 0),
        );

        $minutes = $this->formatMinutes($raw, $meeting);
        $endPos = (int) ($meeting->fresh()?->audio_duration_sec ?? $meeting->transcription_position_sec ?? 0);

        $meeting->forceFill([
            'transcript' => $raw,
            'minutes' => $minutes,
            'transcription_status' => Meeting::TRANSCRIPTION_READY,
            'transcription_progress' => 100,
            'transcription_position_sec' => $endPos,
            'transcription_stage' => $endPos > 0
                ? 'Бэлэн · '.$this->formatClock($endPos)
                : 'Бэлэн',
            'transcription_error' => null,
            'transcribed_at' => now(),
        ])->save();

        return $meeting->refresh();
    }

    public function markFailed(Meeting $meeting, string $message): void
    {
        $pos = (int) ($meeting->transcription_position_sec ?? 0);
        $dur = (int) ($meeting->audio_duration_sec ?? 0);
        $where = $dur > 0
            ? sprintf(' · алдааны байрлал %s / %s', $this->formatClock($pos), $this->formatClock($dur))
            : ($pos > 0 ? ' · бичигдсэн '.$this->formatClock($pos) : '');

        $meeting->forceFill([
            'transcription_status' => Meeting::TRANSCRIPTION_FAILED,
            'transcription_stage' => mb_substr('Алдаа'.$where, 0, 64),
            'transcription_error' => mb_substr(trim($message.$where), 0, 2000),
        ])->save();
    }

    /**
     * Аудиог хэсэгчилж Whisper-ээр боловсруулж, транскриптыг амьд хадгална.
     */
    private function transcribeProgressively(
        Meeting $meeting,
        string $absolutePath,
        ?string $ffmpeg,
        ?float $duration,
    ): string {
        $chunkSec = (int) config('ai.whisper_chunk_seconds', self::CHUNK_SECONDS);
        $chunkSec = max(15, min(60, $chunkSec));

        // ffmpeg/урт байхгүй бол нэг удаа бүтнээр.
        if (! $ffmpeg || ! $duration || $duration <= $chunkSec + 5) {
            $label = $duration
                ? 'Текст бичиж байна… 0:00 / '.$this->formatClock((int) round($duration))
                : 'Текст бичиж байна (Whisper)…';
            $this->setProgress($meeting, Meeting::TRANSCRIPTION_PROCESSING, 50, $label, 0);
            $text = $this->whisperFile($absolutePath, basename($absolutePath));
            $text = $this->collapseRepetitions($text);
            if ($this->looksUnreliableTranscript($text)) {
                throw new RuntimeException(
                    'Аудиог найдвартай монгол текст болгож чадсангүй (буруу хэл/давталт илэрлээ). '
                    .'Дуу чимээгүй, тод монгол яриатай хэсгийг дахин оруулна уу.'
                );
            }
            $end = $duration ? (int) round($duration) : 0;
            $segments = [[
                'id' => uniqid('seg_', true),
                'start' => 0,
                'end' => $end,
                'speaker' => 'Танигдаагүй яригч',
                'text' => $text,
            ]];
            $meeting->forceFill([
                'transcript' => $text,
                'transcript_segments' => $segments,
                'transcription_position_sec' => $end,
            ])->save();

            return $text;
        }

        $segments = [];
        $total = (int) ceil($duration);
        $overlap = 1;
        $start = 0;
        $index = 0;

        while ($start < $duration - 0.5) {
            $len = min($chunkSec, $duration - $start);
            $chunkPath = sys_get_temp_dir().DIRECTORY_SEPARATOR
                .'meeting-chunk-'.$meeting->id.'-'.$index.'-'.uniqid('', true).'.mp3';

            try {
                $this->extractAudioChunk($ffmpeg, $absolutePath, $chunkPath, $start, $len);
                $pct = (int) round(45 + (($start + $len) / $duration) * 45); // 45→90
                $this->setProgress(
                    $meeting,
                    Meeting::TRANSCRIPTION_PROCESSING,
                    $pct,
                    sprintf(
                        'Текст бичиж байна… %s / %s',
                        $this->formatClock((int) round($start + $len)),
                        $this->formatClock($total),
                    ),
                    (int) round($start),
                );

                $chunkText = $this->whisperFile($chunkPath, basename($chunkPath));
                $chunkText = trim($this->collapseRepetitions($chunkText));

                $segStart = (int) round($start);
                $segEnd = (int) round(min($duration, $start + $len));

                // Хэсэг доторх хүчтэй hallucination-ийг алгасна (бүтэн файл унагахгүй).
                if ($chunkText !== '' && (! $this->looksUnreliableTranscript($chunkText) || $this->hasCyrillic($chunkText))) {
                    $segments[] = [
                        'id' => uniqid('seg_', true),
                        'start' => $segStart,
                        'end' => $segEnd,
                        'speaker' => 'Танигдаагүй яригч',
                        'text' => $chunkText,
                    ];
                }

                $combined = trim(implode("\n\n", array_column($segments, 'text')));
                $pos = $segEnd;
                $meeting->forceFill([
                    'transcript' => $combined,
                    'transcript_segments' => $segments,
                    'transcription_position_sec' => $pos,
                ])->save();
            } catch (RuntimeException $e) {
                $meeting->forceFill([
                    'transcription_position_sec' => (int) round($start),
                    'transcript_segments' => $segments,
                    'transcript' => trim(implode("\n\n", array_column($segments, 'text'))),
                ])->save();

                throw new RuntimeException(
                    sprintf(
                        '%s (байрлал %s / %s)',
                        $e->getMessage(),
                        $this->formatClock((int) round($start)),
                        $this->formatClock($total),
                    )
                );
            } finally {
                if (is_file($chunkPath)) {
                    @unlink($chunkPath);
                }
            }

            $start += max(1, $chunkSec - $overlap);
            $index++;
        }

        $raw = trim(implode("\n\n", array_column($segments, 'text')));
        if ($raw === '' || $this->looksUnreliableTranscript($raw)) {
            throw new RuntimeException(
                'Аудиог найдвартай монгол текст болгож чадсангүй. '
                .'Дуу чимээгүй, тод монгол яриатай хэсгийг дахин оруулна уу.'
            );
        }

        return $raw;
    }

    private function extractAudioChunk(
        string $ffmpeg,
        string $input,
        string $output,
        float $start,
        float $length,
    ): void {
        $cmd = [
            $ffmpeg,
            '-y',
            '-ss', (string) max(0, $start),
            '-i', $input,
            '-t', (string) max(0.5, $length),
            '-vn',
            '-acodec', 'libmp3lame',
            '-ar', '16000',
            '-ac', '1',
            '-b:a', '64k',
            $output,
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptor, $pipes, null, null, ['bypass_shell' => true]);
        if (! is_resource($process)) {
            throw new RuntimeException('Аудио хэсэглэлт эхлүүлж чадсангүй.');
        }

        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        if ($code !== 0 || ! is_file($output) || filesize($output) < 1) {
            throw new RuntimeException('Аудио хэсэг үүсгэж чадсангүй. '.mb_substr(trim($stderr), 0, 200));
        }
    }

    public function formatClock(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $s);
        }

        return sprintf('%d:%02d', $m, $s);
    }

    private function hasCyrillic(string $text): bool
    {
        return (bool) preg_match('/\p{Cyrillic}/u', $text);
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

    private function setProgress(
        Meeting $meeting,
        string $status,
        int $percent,
        string $stage,
        ?int $positionSec = null,
    ): void {
        $payload = [
            'transcription_status' => $status,
            'transcription_progress' => max(0, min(100, $percent)),
            'transcription_stage' => mb_substr($stage, 0, 64),
            'transcription_error' => null,
        ];

        if ($positionSec !== null) {
            $payload['transcription_position_sec'] = max(0, $positionSec);
        }

        $meeting->forceFill($payload)->save();
    }

    private function whisperFile(string $absolutePath, string $filename): string
    {
        $apiKey = $this->settings->openaiApiKey();
        if (! $apiKey) {
            throw new RuntimeException($this->configHint());
        }

        $timeout = (int) config('ai.whisper_timeout', 180);
        // Хэсэгчилсэн боловсруулалтад нэг хэсэгт богино timeout.
        $timeout = min($timeout, 90);
        $model = (string) config('ai.whisper_model', 'whisper-1');

        $prompt = (string) config(
            'ai.whisper_prompt',
            'Энэ бол монгол хэлээр яригдсан албан хурлын бичлэг. '
            .'Зөвхөн сонсогдсон монгол үгийг кирилл үсгээр бич. '
            .'Англиар бүү орчуул, үг бүү зохио, үг бүү давта.',
        );

        return $this->callWhisper($apiKey, $model, $timeout, $absolutePath, $filename, $prompt);
    }

    private function callWhisper(
        string $apiKey,
        string $model,
        int $timeout,
        string $absolutePath,
        string $filename,
        string $prompt,
    ): string {
        $stream = fopen($absolutePath, 'r');
        if ($stream === false) {
            throw new RuntimeException('Аудио файлыг уншиж чадсангүй.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('ai.openai_base_url'), '/'))
                ->withToken($apiKey)
                ->connectTimeout(20)
                ->timeout(max(30, $timeout))
                ->attach('file', $stream, $filename)
                ->post('/audio/transcriptions', [
                    'model' => $model,
                    'prompt' => $prompt,
                    'temperature' => '0',
                    'response_format' => 'text',
                ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $response->successful()) {
            throw new RuntimeException('Whisper алдаа: '.$response->status().' '.mb_substr($response->body(), 0, 500));
        }

        $text = trim($response->body());
        if ($text === '') {
            throw new RuntimeException('Whisper хоосон текст буцаалаа.');
        }

        return $text;
    }

    /** Гацсан боловсруулалтыг алдаа болгоно. */
    public function failIfStuck(Meeting $meeting): Meeting
    {
        if (! $meeting->isTranscribing()) {
            return $meeting;
        }

        $timeout = (int) config('ai.whisper_timeout', 180) + 60;
        $updatedAt = $meeting->updated_at;

        if ($updatedAt && $updatedAt->gte(now()->subSeconds($timeout))) {
            return $meeting;
        }

        $this->markFailed(
            $meeting,
            'Боловсруулалт хэт удаан үргэлжиллээ. Дахин оролдоно уу.',
        );

        return $meeting->fresh() ?? $meeting;
    }

    /** Давтагдсан үг/хэллэгийг таслана (Whisper hallucination). */
    private function collapseRepetitions(string $text): string
    {
        $cleaned = preg_replace('/\b([\p{L}\p{N}\']{2,})(?:\s+\1){3,}/ui', '$1', $text) ?? $text;
        $cleaned = preg_replace('/(.{8,40}?)\1{3,}/u', '$1', $cleaned) ?? $cleaned;

        return trim(preg_replace('/[ \t]{2,}/u', ' ', $cleaned) ?? $cleaned);
    }

    private function looksUnreliableTranscript(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return true;
        }

        // Нэг үг хэт олон давтагдах
        if (preg_match('/\b([\p{L}\p{N}\']{2,})(?:\s+\1){5,}/ui', $text)) {
            return true;
        }

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);
        if ($wordCount >= 15) {
            $freq = [];
            foreach ($words as $word) {
                $key = mb_strtolower($word);
                $freq[$key] = ($freq[$key] ?? 0) + 1;
            }
            $top = max($freq);
            if ($top / $wordCount >= 0.35) {
                return true;
            }
        }

        // Монгол хуралд кирилл бараг байхгүй, латин давамгайлсан = буруу хэл/орчуулга
        $cyrillic = preg_match_all('/\p{Cyrillic}/u', $text) ?: 0;
        $latin = preg_match_all('/[A-Za-z]/', $text) ?: 0;
        $letters = $cyrillic + $latin;
        if ($letters >= 20 && $latin >= 12 && ($cyrillic / $letters) < 0.25) {
            return true;
        }

        return false;
    }

    private function formatMinutes(string $transcript, Meeting $meeting): string
    {
        if ($this->looksUnreliableTranscript($transcript)) {
            return "Гарчиг: ".($meeting->title ?: 'Хурлын тэмдэглэл')."\n"
                ."Огноо: ".(optional($meeting->held_at)?->format('Y-m-d H:i') ?: '—')."\n\n"
                ."Тэмдэглэл: Аудиог найдвартай текстлэж чадсангүй. Дахин оруулна уу.";
        }

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
                        .'Зөвхөн монгол (кирилл) утгатай транскриптыг ашигла. '
                        .'Англи текст, давтагдсан утгагүй үг, орчуулга зохиохыг хориглоно. '
                        .'Хэрэв транскрипт утгагүй/буруу хэлтэй бол «Аудиог найдвартай текстлэж чадсангүй» гэж бич. '
                        .'Бүтэц: 1) Гарчиг/сэдэв 2) Огноо 3) Оролцогчид (хэрэв дурдсан бол) '
                        .'4) Хэлэлцсэн асуудал 5) Шийдвэр/үүрэг. '
                        .'Зөвхөн тэмдэглэлийн текстийг буцаа.',
                ],
                [
                    'role' => 'user',
                    'content' => "Гарчиг: {$title}\nОгноо: {$when}\n\nТранскрипт:\n{$transcript}",
                ],
            ], [
                'temperature' => 0.1,
            ]);

            $content = trim((string) ($result['content'] ?? ''));

            return $content !== '' ? $content : $transcript;
        } catch (\Throwable) {
            return $transcript;
        }
    }
}
