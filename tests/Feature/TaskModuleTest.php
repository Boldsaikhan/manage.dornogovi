<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskDocument;
use App\Models\TaskSource;
use App\Models\User;
use App\Models\UserModulePermission;
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
            'period' => '08.01-09.30',
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
                ->where('tasks.0.period', '08.01-09.30')
            );
    }

    public function test_admin_can_store_directive_with_period(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('tasks.store'), [
                'kind' => 'directive',
                'text' => 'Хугацаатай үүрэг',
                'period' => '07.15-08.20',
                'responsible' => 'Б.Тест',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'text' => 'Хугацаатай үүрэг',
            'period' => '07.15-08.20',
        ]);
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
            ->patch(route('tasks.update', $task), ['responsible' => 'Батбаярын Дулмаа'])
            ->assertRedirect();

        // Нэрийг «овгийн эхний үсэг + нэр» хэлбэрт оруулж хадгална.
        $this->assertSame('Б.Дулмаа', $task->fresh()->responsible);
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

    public function test_word_preview_does_not_import_rows_until_confirmed(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['is_admin' => true]);
        $path = $this->makeDirectiveDocx();
        $file = new UploadedFile($path, 'directive.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $this->actingAs($admin)
            ->postJson(route('tasks.documents.preview'), [
                'kind' => 'directive',
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('count', 1);

        // Цонх нь хүснэгтийн толгойд Word-ийн аль багана орохыг санал болгоно.
        $preview = $this->actingAs($admin)
            ->postJson(route('tasks.documents.preview'), [
                'document_id' => TaskDocument::first()->id,
            ])
            ->assertOk()
            ->json();

        $mapping = $preview['mapping'];
        $rawRow = $preview['raw_rows'][0];

        $this->assertSame('Шинэ үүрэг чиглэл', $rawRow[$mapping['text']]);
        $this->assertSame('А.Болд', $rawRow[$mapping['responsible']]);

        $this->assertDatabaseCount('tasks', 0);

        $doc = TaskDocument::first();

        $this->actingAs($admin)
            ->post(route('tasks.documents.import', $doc), [
                'replace' => false,
                'mapping' => $mapping,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('tasks', 1);
        $this->assertSame('Шинэ үүрэг чиглэл', Task::first()->text);
    }

    public function test_admin_can_create_and_delete_custom_section(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('tasks.sources.store'), [
                'name' => 'Шинэ хяналтын хэсэг',
                'copy_from' => 'directive',
            ])
            ->assertRedirect();

        $source = TaskSource::query()->where('name', 'Шинэ хяналтын хэсэг')->first();
        $this->assertNotNull($source);
        $this->assertSame('directive', $source->layout);
        $this->assertFalse($source->isSystem());
        $this->assertSame(
            ['text', 'period', 'responsible', 'collaborator', 'note'],
            $source->columns
        );

        $this->actingAs($admin)
            ->post(route('tasks.store'), [
                'kind' => $source->key,
                'text' => 'Шинэ хэсгийн мөр',
            ])
            ->assertRedirect();

        $this->assertSame(1, $source->tasks()->count());

        $this->actingAs($admin)
            ->get(route('tasks.index', ['kind' => $source->key]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->where('kind', $source->key)
                ->where('source.layout', 'directive')
                ->has('kinds', 4)
            );

        $this->actingAs($admin)
            ->delete(route('tasks.sources.destroy', $source->key))
            ->assertRedirect(route('tasks.index'));

        $this->assertDatabaseMissing('task_sources', ['id' => $source->id]);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_admin_can_create_section_with_selected_table_columns(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('tasks.sources.store'), [
                'name' => 'Сонгосон толгой',
                'columns' => ['sector', 'measure', 'period', 'responsible', 'note'],
            ])
            ->assertRedirect();

        $source = TaskSource::query()->where('name', 'Сонгосон толгой')->first();
        $this->assertNotNull($source);
        $this->assertSame(TaskSource::KEY_PREP_PLAN, $source->layout);
        $this->assertSame(
            ['sector', 'measure', 'period', 'responsible', 'note'],
            $source->columns
        );

        $this->actingAs($admin)
            ->get(route('tasks.index', ['kind' => $source->key]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Uureg/Index')
                ->where('kind', $source->key)
                ->has('source.columns', 5)
                ->where('source.columns.0.key', 'sector')
                ->where('source.columns.1.key', 'measure')
                ->where('source.columns.1.label', 'Арга хэмжээ')
            );
    }

    /**
     * Суурь хэсгийг ч устгах боломжтой болсон — гагцхүү сүүлчийнх нь үлдэнэ.
     * @see TaskSourceColumnsTest
     */
    public function test_system_section_can_be_deleted_while_others_remain(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertGreaterThan(1, TaskSource::query()->count());

        $this->actingAs($admin)
            ->delete(route('tasks.sources.destroy', 'directive'))
            ->assertRedirect();

        $this->assertDatabaseMissing('task_sources', ['key' => 'directive']);
    }

    public function test_edit_own_user_can_update_only_progress_fields_on_assigned_task(): void
    {
        $source = TaskSource::where('key', TaskSource::KEY_DIRECTIVE)->first();
        $user = User::factory()->create(['name' => 'Б.Дөлгөөн']);
        UserModulePermission::create([
            'user_id' => $user->id,
            'module_key' => 'tasks',
            'level' => 'edit_own',
        ]);

        $task = Task::create([
            'task_source_id' => $source->id,
            'text' => 'Хамааралтай үүрэг',
            'responsible' => 'Б.Дөлгөөн',
            'collaborator' => 'Ц.Сансармаа',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.index', ['kind' => 'directive']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canEdit', false)
                ->where('canEditProgress', true)
                ->where('canManage', false)
            );

        $this->actingAs($user)
            ->patch(route('tasks.update', $task), [
                'note' => 'Хэрэгжилт орууллаа',
                'progress' => 45,
            ])
            ->assertRedirect();

        $this->assertSame('Хэрэгжилт орууллаа', $task->fresh()->note);
        $this->assertSame(45, (int) $task->fresh()->progress);

        $this->actingAs($user)
            ->patch(route('tasks.update', $task), ['text' => 'Засварласан гарчиг'])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('tasks.store'), [
                'kind' => 'directive',
                'text' => 'Шинэ мөр',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('tasks.destroy', $task))
            ->assertForbidden();
    }

    private function makeDirectiveDocx(): string
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tr>
        <w:tc><w:p><w:r><w:t>№</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Үүрэг чиглэл</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Хариуцах эзэн</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Хяналт тавих</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>1</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Шинэ үүрэг чиглэл</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>А.Болд</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Б.Дулмаа</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML;

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'directive-preview-'.uniqid('', true).'.docx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return $path;
    }
}
