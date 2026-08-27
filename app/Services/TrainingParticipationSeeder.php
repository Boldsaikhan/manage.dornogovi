<?php

namespace App\Services;

use App\Models\PhoneDirectoryEntry;
use App\Models\Task;
use App\Models\TaskSource;
use App\Support\PersonName;

/**
 * «Сургалтын идэвх оролцоо» хэсэгт хэлтсийн албан хаагч бүрт
 * «сургалтад идэвхтэй оролцох» үүрэг чиглэл үүсгэнэ.
 */
class TrainingParticipationSeeder
{
    public const SECTION_NAME = 'Сургалтын идэвх оролцоо';

    public const SECTION_KEY = 'surgaltyn_idevx_orolcoo';

    public const TASK_TEXT = 'сургалтад идэвхтэй оролцох';

    /**
     * @return array{source_id: int, created: int, skipped: int, people: int}
     */
    public function run(): array
    {
        $source = $this->resolveSource();
        $people = $this->heltesPeople();

        $existing = Task::query()
            ->where('task_source_id', $source->id)
            ->where('text', self::TASK_TEXT)
            ->pluck('responsible')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->all();

        $existingSet = array_fill_keys($existing, true);
        $next = (int) $source->tasks()->max('sort_order');
        $created = 0;
        $skipped = 0;

        foreach ($people as $person) {
            if (isset($existingSet[$person])) {
                $skipped++;

                continue;
            }

            $next++;
            $source->tasks()->create([
                'text' => self::TASK_TEXT,
                'responsible' => $person,
                'collaborator' => null,
                'progress' => 0,
                'sort_order' => $next,
            ]);
            $existingSet[$person] = true;
            $created++;
        }

        return [
            'source_id' => $source->id,
            'created' => $created,
            'skipped' => $skipped,
            'people' => count($people),
        ];
    }

    private function resolveSource(): TaskSource
    {
        $source = TaskSource::query()
            ->where(function ($q) {
                $q->where('key', self::SECTION_KEY)
                    ->orWhere('name', self::SECTION_NAME);
            })
            ->first();

        if ($source) {
            if ($source->key !== self::SECTION_KEY) {
                $source->key = self::SECTION_KEY;
            }
            if ($source->name !== self::SECTION_NAME) {
                $source->name = self::SECTION_NAME;
            }
            if (! $source->layout) {
                $source->layout = TaskSource::KEY_DIRECTIVE;
            }
            $source->save();

            return $source;
        }

        return TaskSource::create([
            'key' => self::SECTION_KEY,
            'name' => self::SECTION_NAME,
            'layout' => TaskSource::KEY_DIRECTIVE,
            'sort_order' => (int) TaskSource::query()->max('sort_order') + 1,
        ]);
    }

    /**
     * @return list<string>
     */
    private function heltesPeople(): array
    {
        $fromDirectory = collect(PhoneDirectoryEntry::peopleOptions())
            ->filter(fn (array $p) => ($p['category'] ?? '') === 'heltes')
            ->pluck('value')
            ->map(fn ($name) => PersonName::shortList((string) $name) ?: trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($fromDirectory !== []) {
            return $fromDirectory;
        }

        // Утасны жагсаалт хоосон бол нэвтэрсэн хэлтсийн хэрэглэгчдээр нөхнө.
        return \App\Models\User::query()
            ->whereNotNull('department_id')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => PersonName::shortList((string) $name) ?: trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
