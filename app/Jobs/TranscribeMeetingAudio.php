<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Services\Ai\MeetingTranscriptionService;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Whisper STT — HTTP хариу илгээсний дараа (afterResponse) ажиллана.
 * Production-д тусдаа queue worker шаардлагагүй.
 */
class TranscribeMeetingAudio
{
    use Queueable;

    public function __construct(public int $meetingId) {}

    public function handle(MeetingTranscriptionService $transcription): void
    {
        @set_time_limit((int) config('ai.whisper_timeout', 180) + 60);
        ignore_user_abort(true);

        $meeting = Meeting::query()->find($this->meetingId);
        if (! $meeting || ! $meeting->audio_path) {
            return;
        }

        try {
            $transcription->transcribeMeeting($meeting);
        } catch (Throwable $e) {
            Log::warning('Meeting transcription failed', [
                'meeting_id' => $this->meetingId,
                'message' => $e->getMessage(),
            ]);
            $transcription->markFailed($meeting->fresh() ?? $meeting, $e->getMessage());
        }
    }
}
