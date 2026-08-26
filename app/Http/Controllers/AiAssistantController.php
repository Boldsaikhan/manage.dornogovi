<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiRateLimiter;
use App\Services\Ai\AiSettings;
use App\Services\Ai\AssistantService;
use App\Support\ModuleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantController extends Controller
{
    public function index(Request $request, AssistantService $assistant, AiRateLimiter $limiter, AiSettings $settings): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), 'ai'), 403);

        $conversationId = $request->integer('conversation_id') ?: null;

        $conversations = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->limit(30)
            ->get(['id', 'title', 'last_message_at', 'created_at']);

        $messagesQuery = AiMessage::query()->where('user_id', $request->user()->id);
        if ($conversationId) {
            $messagesQuery->where('conversation_id', $conversationId);
        } else {
            $latest = $conversations->first();
            $conversationId = $latest?->id;
            if ($conversationId) {
                $messagesQuery->where('conversation_id', $conversationId);
            }
        }

        $messages = $messagesQuery
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'role', 'content', 'meta', 'conversation_id', 'created_at']);

        return Inertia::render('Modules/AiAssistant', [
            'messages' => $messages,
            'conversations' => $conversations,
            'conversationId' => $conversationId,
            'briefing' => $assistant->briefing($request->user()),
            'displayName' => $settings->displayName(),
            'usage' => [
                'limit' => $settings->dailyQuestionLimit(),
                'used' => $limiter->usedToday($request->user()),
                'remaining' => $limiter->remaining($request->user()),
            ],
            'aiEnabled' => $settings->enabled(),
            'providerReady' => $settings->provider() !== 'openai' || $settings->hasOpenAiKey(),
            'canManage' => ModuleAccess::canManage($request->user(), 'ai'),
        ]);
    }

    public function ask(Request $request, AssistantService $assistant): RedirectResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), 'ai'), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
        ]);

        $result = $assistant->ask(
            $request->user(),
            $data['message'],
            $data['conversation_id'] ?? null,
        );

        if (! empty($result['limited'])) {
            return back()->with('success', $result['message']);
        }

        return redirect()
            ->route('ai.index', ['conversation_id' => $result['conversation_id']])
            ->with('success', 'Хариулт бэлэн.');
    }

    /**
     * Хажуугийн чат самбарын өгөгдөл (JSON) — хуудас дахин ачаалахгүйгээр.
     */
    public function panel(Request $request, AiSettings $settings, AiRateLimiter $limiter): JsonResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), 'ai'), 403);

        $conversation = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->first(['id']);

        return response()->json([
            'display_name' => $settings->displayName(),
            'enabled' => $settings->enabled(),
            'conversation_id' => $conversation?->id,
            'messages' => $this->panelMessages($request, $conversation?->id),
            'remaining' => $limiter->remaining($request->user()),
        ]);
    }

    public function panelAsk(Request $request, AssistantService $assistant, AiRateLimiter $limiter): JsonResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), 'ai'), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
        ]);

        $result = $assistant->ask(
            $request->user(),
            $data['message'],
            $data['conversation_id'] ?? null,
        );

        $conversationId = $result['conversation_id'] ?? $data['conversation_id'] ?? null;

        return response()->json([
            'limited' => (bool) ($result['limited'] ?? false),
            'conversation_id' => $conversationId,
            'messages' => $this->panelMessages($request, $conversationId),
            'remaining' => $limiter->remaining($request->user()),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function panelMessages(Request $request, ?int $conversationId): array
    {
        if (! $conversationId) {
            return [];
        }

        return AiMessage::query()
            ->where('user_id', $request->user()->id)
            ->where('conversation_id', $conversationId)
            ->orderBy('id')
            ->limit(60)
            ->get(['id', 'role', 'content', 'meta', 'created_at'])
            ->map(fn (AiMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'meta' => $m->meta,
                'time' => optional($m->created_at)->format('H:i'),
            ])
            ->all();
    }

    public function confirm(Request $request, AssistantService $assistant): RedirectResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), 'ai'), 403);

        $data = $request->validate([
            'type' => ['required', 'string', 'max:64'],
            'payload' => ['required', 'array'],
        ]);

        $result = $assistant->confirmAction($request->user(), $data['type'], $data['payload']);

        return back()->with('success', $result['message'] ?? 'Дууслаа.');
    }

    public function newConversation(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), 'ai'), 403);

        $conversation = AiConversation::create([
            'user_id' => $request->user()->id,
            'title' => 'Шинэ яриа',
            'last_message_at' => now(),
        ]);

        return redirect()->route('ai.index', ['conversation_id' => $conversation->id]);
    }
}
