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
        $timeout = (int) config('ai.whisper_timeout', 180);
        @set_time_limit($timeout + 90);
        ignore_user_abort(true);

        $meeting = Meeting::query()->find($this->meetingId);
        if (! $meeting || ! $meeting->audio_path) {
            return;
        }

        $finished = false;

        register_shutdown_function(function () use (&$finished, $transcription, $meeting) {
            if ($finished) {
                return;
            }

            $fresh = Meeting::query()->find($meeting->id);
            if (! $fresh || ! $fresh->isTranscribing()) {
                return;
            }

            $error = 'Боловсруулалт тасалдлаа (сервер timeout). Дахин оролдоно уу.';
            $last = error_get_last();
            if (is_array($last) && in_array($last['type'] ?? 0, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                $error = 'Боловсруулалт тасалдлаа: '.mb_substr((string) ($last['message'] ?? ''), 0, 400);
            }

            Log::warning('Meeting transcription aborted by shutdown', [
                'meeting_id' => $meeting->id,
                'status' => $fresh->transcription_status,
            ]);

            $transcription->markFailed($fresh, $error);
        });

        try {
            $transcription->transcribeMeeting($meeting);
            $finished = true;
        } catch (Throwable $e) {
            $finished = true;
            Log::warning('Meeting transcription failed', [
                'meeting_id' => $this->meetingId,
                'message' => $e->getMessage(),
            ]);
            $transcription->markFailed($meeting->fresh() ?? $meeting, $e->getMessage());
        }
    }
}
