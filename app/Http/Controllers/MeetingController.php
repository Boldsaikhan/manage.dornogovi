<?php

namespace App\Http\Controllers;

use App\Jobs\TranscribeMeetingAudio;
use App\Models\Meeting;
use App\Services\Ai\MeetingTranscriptionService;
use App\Services\Push\EmployeePushNotifier;
use App\Support\ModuleAccess;
use App\Support\ModuleOwnScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingController extends Controller
{
    public const MODULE = 'meetings';

    public function index(Request $request, MeetingTranscriptionService $transcription): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);

        $query = Meeting::query()
            ->with('creator:id,name')
            ->orderByDesc('held_at')
            ->orderByDesc('id');

        ModuleOwnScope::apply($query, $request->user(), self::MODULE);

        $meetings = $query->get()->map(function (Meeting $m) use ($transcription) {
            if ($m->isTranscribing()) {
                $m = $transcription->failIfStuck($m);
            }

            return $this->serialize($m);
        });

        return Inertia::render('Modules/Meetings', [
            'meetings' => $meetings,
            'canManage' => ModuleAccess::canEdit($request->user(), self::MODULE),
            'sttReady' => $transcription->isReady(),
            'sttHint' => $transcription->configHint(),
            'maxUploadMb' => (int) config('ai.whisper_max_mb', 25),
            'hasProcessing' => $meetings->contains(fn (array $row) => in_array($row['transcription_status'], [
                Meeting::TRANSCRIPTION_PENDING,
                Meeting::TRANSCRIPTION_CONVERTING,
                Meeting::TRANSCRIPTION_PROCESSING,
            ], true)),
        ]);
    }

    public function store(Request $request, MeetingTranscriptionService $transcription): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);
        ModuleOwnScope::assertCanCreate($request->user(), self::MODULE);

        $maxMb = (int) config('ai.whisper_max_mb', 25);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'held_at' => ['nullable', 'date'],
            'minutes' => ['nullable', 'string'],
            'transcript' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'final'])],
            'audio' => $this->audioRules($maxMb, false),
        ]);

        $hasAudio = $request->hasFile('audio');

        if ($hasAudio && ! $transcription->isReady()) {
            return back()
                ->withInput()
                ->with('error', $transcription->configHint());
        }

        $meeting = Meeting::query()->create([
            'title' => $data['title'],
            'held_at' => $data['held_at'] ?? now(),
            'minutes' => $data['minutes'] ?? null,
            'transcript' => $data['transcript'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'transcription_status' => Meeting::TRANSCRIPTION_NONE,
            'created_by' => $request->user()->id,
        ]);

        if ($hasAudio) {
            $this->storeAudio($meeting, $request->file('audio'));
            $meeting->forceFill([
                'transcription_status' => Meeting::TRANSCRIPTION_PENDING,
                'transcription_progress' => 1,
                'transcription_stage' => 'Дараалалд орлоо…',
                'transcription_error' => null,
            ])->save();
            TranscribeMeetingAudio::dispatchAfterResponse($meeting->id);
        }

        app(EmployeePushNotifier::class)->notifyUsers(
            array_filter([(int) $meeting->created_by]),
            [
                'title' => 'Хурлын тэмдэглэл',
                'body' => (string) $meeting->title,
                'url' => '/modules/meetings',
                'tag' => 'meeting',
            ],
        );

        $message = $hasAudio
            ? 'Аудио хүлээн авлаа. Текст рүү хөрвүүлж байна…'
            : 'Хурлын тэмдэглэл хадгалагдлаа.';

        return redirect()
            ->route('meetings.index', ['open' => $meeting->id])
            ->with('success', $message);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $meeting), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'held_at' => ['nullable', 'date'],
            'minutes' => ['nullable', 'string'],
            'transcript' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'final'])],
        ]);

        $meeting->fill([
            'title' => $data['title'],
            'held_at' => $data['held_at'] ?? $meeting->held_at,
            'minutes' => $data['minutes'] ?? null,
            'transcript' => $data['transcript'] ?? null,
            'status' => $data['status'] ?? $meeting->status,
        ])->save();

        return back()->with('success', 'Тэмдэглэл шинэчлэгдлээ.');
    }

    public function destroy(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $meeting), 403);

        $this->deleteAudioFile($meeting);
        $meeting->delete();

        return redirect()
            ->route('meetings.index')
            ->with('success', 'Тэмдэглэл устгалаа.');
    }

    public function retry(Request $request, Meeting $meeting, MeetingTranscriptionService $transcription): RedirectResponse
    {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $meeting), 403);

        if (! $meeting->audio_path) {
            return back()->with('error', 'Дахин боловсруулах аудио алга.');
        }

        if (! $transcription->isReady()) {
            return back()->with('error', $transcription->configHint());
        }

        $meeting->forceFill([
            'transcription_status' => Meeting::TRANSCRIPTION_PENDING,
            'transcription_progress' => 1,
            'transcription_stage' => 'Дахин боловсруулж байна…',
            'transcription_error' => null,
        ])->save();

        TranscribeMeetingAudio::dispatchAfterResponse($meeting->id);

        return back()->with('success', 'Дахин боловсруулж байна…');
    }

    public function uploadAudio(
        Request $request,
        Meeting $meeting,
        MeetingTranscriptionService $transcription,
    ): RedirectResponse {
        abort_unless(ModuleAccess::canEdit($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $meeting), 403);

        if (! $transcription->isReady()) {
            return back()->with('error', $transcription->configHint());
        }

        $maxMb = (int) config('ai.whisper_max_mb', 25);
        $request->validate([
            'audio' => $this->audioRules($maxMb, true),
        ]);

        $this->deleteAudioFile($meeting);
        $this->storeAudio($meeting, $request->file('audio'));
        $meeting->forceFill([
            'transcription_status' => Meeting::TRANSCRIPTION_PENDING,
            'transcription_progress' => 1,
            'transcription_stage' => 'Дараалалд орлоо…',
            'transcription_error' => null,
            'transcript' => null,
            'transcribed_at' => null,
        ])->save();

        TranscribeMeetingAudio::dispatchAfterResponse($meeting->id);

        return back()->with('success', 'Аудио орууллаа. MP3 болгож текст рүү хөрвүүлнэ…');
    }

    public function status(
        Request $request,
        Meeting $meeting,
        MeetingTranscriptionService $transcription,
    ): JsonResponse {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $meeting), 403);

        $fresh = $meeting->fresh() ?? $meeting;
        $fresh = $transcription->failIfStuck($fresh);

        return response()->json($this->serialize($fresh));
    }

    public function downloadAudio(Request $request, Meeting $meeting): StreamedResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), self::MODULE), 403);
        abort_unless(ModuleOwnScope::allows($request->user(), self::MODULE, $meeting), 403);
        abort_unless($meeting->audio_path && Storage::disk('local')->exists($meeting->audio_path), 404);

        return Storage::disk('local')->download(
            $meeting->audio_path,
            $meeting->audio_original_name ?: 'meeting-audio',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Meeting $m): array
    {
        return [
            'id' => $m->id,
            'title' => $m->title,
            'held_at' => optional($m->held_at)?->format('Y-m-d\TH:i'),
            'held_at_label' => optional($m->held_at)?->format('Y-m-d H:i'),
            'minutes' => $m->minutes,
            'transcript' => $m->transcript,
            'status' => $m->status,
            'status_label' => $m->statusLabel(),
            'transcription_status' => $m->transcription_status,
            'transcription_label' => $m->transcriptionLabel(),
            'transcription_progress' => (int) ($m->transcription_progress ?? 0),
            'transcription_position_sec' => (int) ($m->transcription_position_sec ?? 0),
            'transcription_position_label' => $m->transcriptionPositionLabel(),
            'transcription_stage' => $m->transcription_stage,
            'transcription_error' => $m->transcription_error,
            'has_audio' => filled($m->audio_path),
            'audio_name' => $m->audio_original_name,
            'audio_size' => $m->audio_size,
            'audio_duration_sec' => $m->audio_duration_sec,
            'audio_duration_label' => $m->audioDurationLabel(),
            'transcribed_at' => optional($m->transcribed_at)?->format('Y-m-d H:i'),
            'updated_at' => optional($m->updated_at)?->toIso8601String(),
            'creator' => $m->creator?->name,
            'created_at' => optional($m->created_at)?->format('Y-m-d H:i'),
        ];
    }

    private function storeAudio(Meeting $meeting, $file): void
    {
        $path = $file->store('meeting-audio/'.($meeting->id ?: 'tmp'), 'local');

        $meeting->forceFill([
            'audio_path' => $path,
            'audio_original_name' => $file->getClientOriginalName(),
            'audio_mime' => $file->getMimeType(),
            'audio_size' => $file->getSize(),
        ]);
    }

    private function deleteAudioFile(Meeting $meeting): void
    {
        if ($meeting->audio_path && Storage::disk('local')->exists($meeting->audio_path)) {
            Storage::disk('local')->delete($meeting->audio_path);
        }
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    private function audioRules(int $maxMb, bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:'.($maxMb * 1024),
            'extensions:mp3,wav,m4a,webm,ogg,flac,mp4,mpeg,mpga,oga,opus,wma,asf',
        ];
    }
}
