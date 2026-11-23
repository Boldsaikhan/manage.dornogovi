<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meeting extends Model
{
    public const TRANSCRIPTION_NONE = 'none';

    public const TRANSCRIPTION_PENDING = 'pending';

    public const TRANSCRIPTION_CONVERTING = 'converting';

    public const TRANSCRIPTION_PROCESSING = 'processing';

    public const TRANSCRIPTION_READY = 'ready';

    public const TRANSCRIPTION_FAILED = 'failed';

    public const TRANSCRIPTION_LABELS = [
        self::TRANSCRIPTION_NONE => 'Аудиогүй',
        self::TRANSCRIPTION_PENDING => 'Хүлээгдэж байна',
        self::TRANSCRIPTION_CONVERTING => 'MP3 хөрвүүлж байна',
        self::TRANSCRIPTION_PROCESSING => 'Текст рүү хөрвүүлж байна',
        self::TRANSCRIPTION_READY => 'Бэлэн',
        self::TRANSCRIPTION_FAILED => 'Алдаа',
    ];

    public const STATUS_LABELS = [
        'draft' => 'Ноорог',
        'final' => 'Батлагдсан',
    ];

    protected $fillable = [
        'title',
        'held_at',
        'attendees',
        'minutes',
        'transcript',
        'audio_path',
        'audio_original_name',
        'audio_mime',
        'audio_size',
        'audio_duration_sec',
        'transcription_status',
        'transcription_progress',
        'transcription_position_sec',
        'transcription_stage',
        'transcription_error',
        'transcribed_at',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'held_at' => 'datetime',
            'attendees' => 'array',
            'transcribed_at' => 'datetime',
            'audio_size' => 'integer',
            'audio_duration_sec' => 'integer',
            'transcription_progress' => 'integer',
            'transcription_position_sec' => 'integer',
        ];
    }

    public function audioDurationLabel(): ?string
    {
        if (! $this->audio_duration_sec) {
            return null;
        }

        return $this->formatClock((int) $this->audio_duration_sec);
    }

    public function transcriptionPositionLabel(): ?string
    {
        if ($this->transcription_position_sec === null) {
            return null;
        }

        return $this->formatClock((int) $this->transcription_position_sec);
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isTranscribing(): bool
    {
        return in_array($this->transcription_status, [
            self::TRANSCRIPTION_PENDING,
            self::TRANSCRIPTION_CONVERTING,
            self::TRANSCRIPTION_PROCESSING,
        ], true);
    }

    public function transcriptionLabel(): string
    {
        return self::TRANSCRIPTION_LABELS[$this->transcription_status]
            ?? (string) $this->transcription_status;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }
}
