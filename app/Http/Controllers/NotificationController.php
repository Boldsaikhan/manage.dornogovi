<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Services\Notifications\OpenTaskAlertSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        app(OpenTaskAlertSync::class)->sync($user);

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
}
