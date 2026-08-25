<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskDocument;
use App\Models\TaskSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
    }

    public function test_page_lists_directive_table(): void
    {
        $source = TaskSource::where('key', TaskSource::KEY_DIRECTIVE)->first();
        $task = Task::create([
            'task_source_id' => $source->id,
            'text' => 'Шинэ үүрэг',
            'responsible' => 'А.Болд',
            'collaborator' => 'Хяналт',
            'sort_order' => 1,
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->where('kind', 'directive')
                ->has('tasks', 1)
                ->where('tasks.0.text', $task->text)
            );
    }

    public function test_admin_can_store_and_update_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('tasks.store'), [
                'kind' => 'prep_plan',
                'sector' => 'Зудын эсрэг',
                'text' => 'Тэжээл нөөцлөх',
                'period' => '08.01-09.30',
                'responsible' => 'Хэлтэс',
                'collaborator' => 'Нэгж',
            ])
            ->assertRedirect();

        $task = Task::first();
        $this->assertNotNull($task);
        $this->assertSame('Тэжээл нөөцлөх', $task->text);

        $this->actingAs($admin)
            ->patch(route('tasks.update', $task), ['responsible' => 'Шинэ эзэн'])
            ->assertRedirect();

        $this->assertSame('Шинэ эзэн', $task->fresh()->responsible);
    }

    public function test_admin_can_upload_and_download_word_document(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['is_admin' => true]);
        $file = UploadedFile::fake()->create('uureg.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->actingAs($admin)
            ->post(route('tasks.documents.store'), [
                'kind' => 'directive',
                'file' => $file,
            ])
            ->assertRedirect();

        $doc = TaskDocument::first();
        $this->assertNotNull($doc);
        $this->assertSame('uureg.docx', $doc->original_name);
        Storage::disk('local')->assertExists($doc->path);

        $this->actingAs($admin)
            ->get(route('tasks.documents.download', $doc))
            ->assertOk();

        $this->actingAs($admin)
            ->delete(route('tasks.documents.destroy', $doc))
            ->assertRedirect();

        $this->assertDatabaseCount('task_documents', 0);
        Storage::disk('local')->assertMissing($doc->path);
    }
}
