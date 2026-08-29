<?php

namespace App\Services\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ModuleAccess;
use App\Support\ModuleOwnScope;
use App\Support\ModuleVisibility;

/**
 * Өөрт хамаатай нээлттэй үүргийг in-app bell-д харуулна (push subscription шаардахгүй).
 */
class OpenTaskAlertSync
{
    public function sync(User $user): void
    {
        if (! ModuleVisibility::isEnabled('tasks')) {
            return;
        }

        if (! ModuleAccess::canView($user, 'dept_dashboard') && ! ModuleAccess::canView($user, 'tasks')) {
            return;
        }

        $query = Task::query()
            ->with('source:id,key,name')
            ->where('progress', '<', 100);

        ModuleOwnScope::restrictTasksToAssignee($query, $user);

        $open = $query
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($open as $task) {
            $tag = 'task-open-'.$task->id;

            $exists = UserNotification::query()
                ->where('user_id', $user->id)
                ->where('tag', $tag)
                ->exists();

            if ($exists) {
                continue;
            }

            $canOpenModule = ModuleAccess::canView($user, 'tasks');

            UserNotification::query()->create([
                'user_id' => $user->id,
                'title' => 'Үүрэг даалгавар',
                'body' => mb_substr(trim((string) $task->text), 0, 120) ?: 'Нээлттэй үүрэг байна.',
                'url' => ($canOpenModule && $task->source?->key)
                    ? '/uureg?kind='.$task->source->key
                    : '/dept-dashboard',
                'tag' => $tag,
            ]);
        }
    }
}
