<?php

namespace App\Services\Ai\Tools;

use App\Models\Task;
use App\Models\TaskSource;
use App\Models\User;
use App\Support\ModuleOwnScope;

class TaskTools
{
    public function search(User $user, array $args = []): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $kind = $args['kind'] ?? null;

        $query = Task::query()->with('source:id,key,name')->orderBy('sort_order')->orderBy('id')->limit(20);
        ModuleOwnScope::apply($query, $user, 'tasks');
        if ($kind) {
            $query->whereHas('source', fn ($s) => $s->where('key', $kind));
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('text', 'like', "%{$q}%")
                    ->orWhere('responsible', 'like', "%{$q}%")
                    ->orWhere('sector', 'like', "%{$q}%")
                    ->orWhere('collaborator', 'like', "%{$q}%");
            });
        }

        return [
            'items' => $query->get()->map(fn (Task $t) => $this->map($t))->all(),
            'source' => 'tasks',
        ];
    }

    public function mine(User $user, array $args = []): array
    {
        $name = $user->name;
        $items = Task::query()
            ->with('source:id,key,name')
            ->where(function ($w) use ($name) {
                $w->where('responsible', 'like', "%{$name}%")
                    ->orWhere('collaborator', 'like', "%{$name}%");
            });
        ModuleOwnScope::apply($items, $user, 'tasks');
        $items = $items
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (Task $t) => $this->map($t))
            ->all();

        return ['items' => $items, 'source' => 'tasks'];
    }

    public function overdue(User $user, array $args = []): array
    {
        // Хугацаа текстээр хадгалагддаг тул progress < 100-ыг "нээлттэй" гэж үзнэ.
        $openQuery = Task::query()
            ->with('source:id,key,name')
            ->where('progress', '<', 100);
        ModuleOwnScope::apply($openQuery, $user, 'tasks');
        $open = $openQuery
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (Task $t) => $this->map($t))
            ->all();

        $openCountQuery = Task::query()->where('progress', '<', 100);
        ModuleOwnScope::apply($openCountQuery, $user, 'tasks');

        return [
            'open_count' => $openCountQuery->count(),
            'items' => $open,
            'note' => 'Хугацаа хэтэрсэнийг автоматаар тооцох огноо байхгүй тул дуусаагүй үүргүүдийг харууллаа.',
            'source' => 'tasks',
        ];
    }

    public function report(User $user, array $args = []): array
    {
        $base = Task::query();
        ModuleOwnScope::apply($base, $user, 'tasks');

        $total = (clone $base)->count();
        $done = (clone $base)->where('progress', '>=', 100)->count();
        $open = (clone $base)->where('progress', '<', 100)->count();
        $avg = (int) round((float) ((clone $base)->avg('progress') ?? 0));

        $bySource = TaskSource::query()->withCount(['tasks' => function ($q) use ($user) {
            ModuleOwnScope::apply($q, $user, 'tasks');
        }])->orderBy('sort_order')->get()->map(fn (TaskSource $s) => [
            'name' => $s->name,
            'key' => $s->key,
            'count' => $s->tasks_count,
        ])->all();

        return [
            'total' => $total,
            'done' => $done,
            'in_progress' => $open,
            'completion_percent' => $avg,
            'by_source' => $bySource,
            'source' => 'tasks',
        ];
    }

    private function map(Task $t): array
    {
        $params = array_filter(['kind' => $t->source?->key]);

        return [
            'id' => $t->id,
            'text' => $t->text,
            'period' => $t->period,
            'responsible' => $t->responsible,
            'collaborator' => $t->collaborator,
            'sector' => $t->sector,
            'progress' => $t->progress,
            'kind' => $t->source?->name,
            'kind_key' => $t->source?->key,
            'route' => 'tasks.index',
            'params' => $params,
            'href' => route('tasks.index', $params),
        ];
    }
}
