<?php

namespace Tests\Feature;

use App\Jobs\TranscribeMeetingAudio;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Ai\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MeetingMinutesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_guest_cannot_open_meetings(): void
    {
        $this->get(route('meetings.index'))->assertRedirect(route('login'));
    }

    public function test_admin_sees_meetings_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('meetings.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Modules/Meetings')
                ->has('meetings')
                ->where('canManage', true)
                ->where('sttReady', false)
            );
    }

    public function test_can_create_manual_minutes_without_audio(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('meetings.store'), [
                'title' => 'Шуурхай хурал',
                'held_at' => '2026-11-23 10:00',
                'minutes' => 'Шийдвэр: туршилт',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'title' => 'Шуурхай хурал',
            'transcription_status' => Meeting::TRANSCRIPTION_NONE,
            'created_by' => $admin->id,
        ]);
    }

    public function test_audio_upload_requires_openai_key(): void
    {
        Bus::fake();
        Storage::fake('local');
        $admin = $this->admin();

        $file = UploadedFile::fake()->create('meeting.mp3', 200, 'audio/mpeg');

        $this->actingAs($admin)
            ->from(route('meetings.index'))
            ->post(route('meetings.store'), [
                'title' => 'Аудиотой хурал',
                'audio' => $file,
            ])
            ->assertRedirect(route('meetings.index'))
            ->assertSessionHas('error');

        Bus::assertNothingDispatched();
        $this->assertDatabaseMissing('meetings', ['title' => 'Аудиотой хурал']);
    }

    public function test_audio_upload_dispatches_transcription_job(): void
    {
        Bus::fake();
        Storage::fake('local');
        $admin = $this->admin();

        app(AiSettings::class)->setOpenAiApiKey('sk-test-meeting-key');
        app(AiSettings::class)->set(AiSettings::KEY_PROVIDER, 'openai');

        $file = UploadedFile::fake()->create('meeting.mp3', 200, 'audio/mpeg');

        $this->actingAs($admin)
            ->post(route('meetings.store'), [
                'title' => 'Аудиотой хурал',
                'held_at' => '2026-11-23 11:00',
                'audio' => $file,
            ])
            ->assertRedirect();

        $meeting = Meeting::query()->where('title', 'Аудиотой хурал')->first();
        $this->assertNotNull($meeting);
        $this->assertSame(Meeting::TRANSCRIPTION_PENDING, $meeting->transcription_status);
        $this->assertNotNull($meeting->audio_path);
        Storage::disk('local')->assertExists($meeting->audio_path);

        Bus::assertDispatched(TranscribeMeetingAudio::class, fn (TranscribeMeetingAudio $job) => $job->meetingId === $meeting->id);
    }

    public function test_wma_audio_upload_is_accepted(): void
    {
        Bus::fake();
        Storage::fake('local');
        $admin = $this->admin();

        app(AiSettings::class)->setOpenAiApiKey('sk-test-meeting-key');
        app(AiSettings::class)->set(AiSettings::KEY_PROVIDER, 'openai');

        $file = UploadedFile::fake()->create('250126_0705.WMA', 200, 'audio/x-ms-wma');

        $this->actingAs($admin)
            ->post(route('meetings.store'), [
                'title' => 'WMA хурал',
                'held_at' => '2026-11-23 11:00',
                'audio' => $file,
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors('audio');

        $meeting = Meeting::query()->where('title', 'WMA хурал')->first();
        $this->assertNotNull($meeting);
        $this->assertSame('250126_0705.WMA', $meeting->audio_original_name);
        Bus::assertDispatched(TranscribeMeetingAudio::class);
    }

    public function test_transcription_job_writes_minutes(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        app(AiSettings::class)->setOpenAiApiKey('sk-test-meeting-key');
        app(AiSettings::class)->set(AiSettings::KEY_PROVIDER, 'openai');

        $path = 'meeting-audio/1/sample.webm';
        Storage::disk('local')->put($path, 'fake-audio-bytes');

        $meeting = Meeting::query()->create([
            'title' => 'Тест хурал',
            'held_at' => now(),
            'status' => 'draft',
            'audio_path' => $path,
            'audio_original_name' => 'sample.webm',
            'audio_mime' => 'audio/webm',
            'audio_size' => 16,
            'transcription_status' => Meeting::TRANSCRIPTION_PENDING,
            'created_by' => $admin->id,
        ]);

        Http::fake([
            '*/audio/transcriptions' => Http::response('Сайн байна уу. Хурал эхэллээ.', 200),
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => "Гарчиг: Тест хурал\nШийдвэр: Хурал эхэлсэн.",
                    ],
                ]],
            ], 200),
        ]);

        (new TranscribeMeetingAudio($meeting->id))->handle(app(\App\Services\Ai\MeetingTranscriptionService::class));

        $meeting->refresh();
        $this->assertSame(Meeting::TRANSCRIPTION_READY, $meeting->transcription_status);
        $this->assertSame(100, (int) $meeting->transcription_progress);
        $this->assertStringContainsString('Хурал эхэллээ', (string) $meeting->transcript);
        $this->assertStringContainsString('Шийдвэр', (string) $meeting->minutes);
    }

    public function test_empty_whisper_response_fails_clearly(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        app(AiSettings::class)->setOpenAiApiKey('sk-test-meeting-key');
        app(AiSettings::class)->set(AiSettings::KEY_PROVIDER, 'openai');

        $path = 'meeting-audio/1/silent.mp3';
        Storage::disk('local')->put($path, 'fake-audio-bytes');

        $meeting = Meeting::query()->create([
            'title' => 'Чимээгүй',
            'held_at' => now(),
            'status' => 'draft',
            'audio_path' => $path,
            'audio_original_name' => 'silent.mp3',
            'audio_mime' => 'audio/mpeg',
            'audio_size' => 16,
            'transcription_status' => Meeting::TRANSCRIPTION_PENDING,
            'created_by' => $admin->id,
        ]);

        Http::fake([
            '*/audio/transcriptions' => Http::response('', 200),
        ]);

        (new TranscribeMeetingAudio($meeting->id))->handle(app(\App\Services\Ai\MeetingTranscriptionService::class));

        $meeting->refresh();
        $this->assertSame(Meeting::TRANSCRIPTION_FAILED, $meeting->transcription_status);
        $this->assertStringContainsString('текст олдсонгүй', (string) $meeting->transcription_error);
    }

    public function test_hallucinated_chinese_transcript_is_rejected(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        app(AiSettings::class)->setOpenAiApiKey('sk-test-meeting-key');
        app(AiSettings::class)->set(AiSettings::KEY_PROVIDER, 'openai');

        $path = 'meeting-audio/1/zh.mp3';
        Storage::disk('local')->put($path, 'fake-audio-bytes');

        $meeting = Meeting::query()->create([
            'title' => 'Хятад hallucination',
            'held_at' => now(),
            'status' => 'draft',
            'audio_path' => $path,
            'audio_original_name' => 'zh.mp3',
            'audio_mime' => 'audio/mpeg',
            'audio_size' => 16,
            'transcription_status' => Meeting::TRANSCRIPTION_PENDING,
            'created_by' => $admin->id,
        ]);

        Http::fake([
            '*/audio/transcriptions' => Http::response('本期视频就拍到这里了. 谢谢观看. Менің тіл.', 200),
        ]);

        (new TranscribeMeetingAudio($meeting->id))->handle(app(\App\Services\Ai\MeetingTranscriptionService::class));

        $meeting->refresh();
        $this->assertSame(Meeting::TRANSCRIPTION_FAILED, $meeting->transcription_status);
        $this->assertStringContainsString('найдвартай', (string) $meeting->transcription_error);
    }

    public function test_prompt_echo_transcript_is_rejected(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        app(AiSettings::class)->setOpenAiApiKey('sk-test-meeting-key');
        app(AiSettings::class)->set(AiSettings::KEY_PROVIDER, 'openai');

        $path = 'meeting-audio/1/echo.mp3';
        Storage::disk('local')->put($path, 'fake-audio-bytes');

        $meeting = Meeting::query()->create([
            'title' => 'Prompt echo',
            'held_at' => now(),
            'status' => 'draft',
            'audio_path' => $path,
            'audio_original_name' => 'echo.mp3',
            'audio_mime' => 'audio/mpeg',
            'audio_size' => 16,
            'transcription_status' => Meeting::TRANSCRIPTION_PENDING,
            'created_by' => $admin->id,
        ]);

        Http::fake([
            '*/audio/transcriptions' => Http::response(
                'Зөвхөн сонсогдсон монгол үгийг кирилл үсгээр бич. Англиар бүү орчуул.',
                200
            ),
        ]);

        (new TranscribeMeetingAudio($meeting->id))->handle(app(\App\Services\Ai\MeetingTranscriptionService::class));

        $meeting->refresh();
        $this->assertSame(Meeting::TRANSCRIPTION_FAILED, $meeting->transcription_status);
    }

    public function test_hallucinated_english_transcript_is_rejected(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        app(AiSettings::class)->setOpenAiApiKey('sk-test-meeting-key');
        app(AiSettings::class)->set(AiSettings::KEY_PROVIDER, 'openai');

        $path = 'meeting-audio/1/bad.mp3';
        Storage::disk('local')->put($path, 'fake-audio-bytes');

        $meeting = Meeting::query()->create([
            'title' => 'Муу аудио',
            'held_at' => now(),
            'status' => 'draft',
            'audio_path' => $path,
            'audio_original_name' => 'bad.mp3',
            'audio_mime' => 'audio/mpeg',
            'audio_size' => 16,
            'transcription_status' => Meeting::TRANSCRIPTION_PENDING,
            'created_by' => $admin->id,
        ]);

        $loop = 'India has the best technology. '.str_repeat('China ', 40);

        Http::fake([
            '*/audio/transcriptions' => Http::response($loop, 200),
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'should-not-matter']]],
            ], 200),
        ]);

        (new TranscribeMeetingAudio($meeting->id))->handle(app(\App\Services\Ai\MeetingTranscriptionService::class));

        $meeting->refresh();
        $this->assertSame(Meeting::TRANSCRIPTION_FAILED, $meeting->transcription_status);
        $this->assertStringContainsString('найдвартай', (string) $meeting->transcription_error);
    }

    public function test_can_update_transcript_segments(): void
    {
        $admin = $this->admin();
        $meeting = Meeting::query()->create([
            'title' => 'Сегмент',
            'held_at' => now(),
            'status' => 'draft',
            'transcript' => 'хуучин',
            'transcription_status' => Meeting::TRANSCRIPTION_READY,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('meetings.update', $meeting), [
                'title' => 'Сегмент',
                'status' => 'draft',
                'transcript_segments' => [
                    [
                        'id' => 'seg_1',
                        'start' => 0,
                        'end' => 30,
                        'speaker' => 'Яригч 1',
                        'text' => 'Сайн байна уу',
                    ],
                ],
            ])
            ->assertRedirect();

        $meeting->refresh();
        $this->assertSame('Сайн байна уу', $meeting->transcript);
        $this->assertSame('Яригч 1', $meeting->transcript_segments[0]['speaker'] ?? null);
    }

    public function test_status_includes_duration_fields(): void
    {
        $admin = $this->admin();
        $meeting = Meeting::query()->create([
            'title' => 'Урттай',
            'held_at' => now(),
            'status' => 'draft',
            'audio_duration_sec' => 125,
            'transcription_position_sec' => 40,
            'transcription_status' => Meeting::TRANSCRIPTION_PROCESSING,
            'transcription_progress' => 50,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('meetings.status', $meeting))
            ->assertOk()
            ->assertJsonPath('audio_duration_sec', 125)
            ->assertJsonPath('audio_duration_label', '2:05')
            ->assertJsonPath('transcription_position_label', '0:40');
    }

    public function test_can_update_and_delete_meeting(): void
    {
        $admin = $this->admin();
        $meeting = Meeting::query()->create([
            'title' => 'Хуучин',
            'held_at' => now(),
            'minutes' => 'А',
            'status' => 'draft',
            'transcription_status' => Meeting::TRANSCRIPTION_NONE,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('meetings.update', $meeting), [
                'title' => 'Шинэчилсэн',
                'minutes' => 'Б',
                'status' => 'final',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'title' => 'Шинэчилсэн',
            'status' => 'final',
        ]);

        $this->actingAs($admin)
            ->delete(route('meetings.destroy', $meeting))
            ->assertRedirect(route('meetings.index'));

        $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
    }
}
