<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ModuleAccess;
use App\Support\PersonName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->syncOpenTaskAlerts($user);

        $items = UserNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn (UserNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url ?: '/dept-dashboard',
                'at' => optional($n->created_at)?->toIso8601String(),
                'read' => $n->read_at !== null,
            ]);

        $unread = UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'items' => $items,
            'unread' => $unread,
        ]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);
        $notification->markRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function clear(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Өөрт хамаатай нээлттэй үүргийг bell-д харуулах (NavBadges-тай ижил нэрээр).
     * Push subscription байхгүй байсан ч мэдэгдэл харагдана.
     */
    private function syncOpenTaskAlerts(User $user): void
    {
        if (! ModuleAccess::canView($user, 'tasks')) {
            return;
        }

        $variants = array_values(array_unique(array_filter([
            trim((string) $user->name),
            PersonName::short($user->name),
        ], fn ($n) => $n !== '')));

        if (! $variants) {
            return;
        }

        $open = Task::query()
            ->with('source:id,key,name')
            ->where('progress', '<', 100)
            ->where(function (Builder $w) use ($variants) {
                foreach (['responsible', 'collaborator'] as $column) {
                    foreach ($variants as $name) {
                        $w->orWhere($column, 'like', '%'.$name.'%');
                    }
                }
            })
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

            UserNotification::query()->create([
                'user_id' => $user->id,
                'title' => 'Үүрэг даалгавар',
                'body' => mb_substr(trim((string) $task->text), 0, 120) ?: 'Нээлттэй үүрэг байна.',
                'url' => $task->source?->key
                    ? '/uureg?kind='.$task->source->key
                    : '/uureg',
                'tag' => $tag,
            ]);
        }
    }
}
