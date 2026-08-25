<?php

namespace App\Http\Controllers;

use App\Models\AiMessage;
use App\Models\Decree;
use App\Models\Leave;
use App\Models\Plan;
use App\Models\Regulation;
use App\Models\Task;
use App\Support\ModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(ModuleAccess::canView($request->user(), 'ai'), 403);

        $messages = AiMessage::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'role', 'content', 'created_at']);

        return Inertia::render('Modules/AiAssistant', [
            'messages' => $messages,
            'canManage' => ModuleAccess::canManage($request->user(), 'ai'),
        ]);
    }

    public function ask(Request $request): RedirectResponse
    {
        abort_unless(ModuleAccess::canView($request->user(), 'ai'), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        AiMessage::create([
            'user_id' => $request->user()->id,
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $answer = $this->answerFromSystemData($data['message']);

        AiMessage::create([
            'user_id' => $request->user()->id,
            'role' => 'assistant',
            'content' => $answer,
            'meta' => ['source' => 'local_index'],
        ]);

        return back();
    }

    /**
     * Системийн дотоод өгөгдлөөс энгийн хариу бүтээнэ (гадны LLM шаардлагагүй).
     */
    private function answerFromSystemData(string $question): string
    {
        $q = mb_strtolower($question);
        $parts = [];

        if (str_contains($q, 'чөлөө') || str_contains($q, 'амралт')) {
            $count = Leave::query()->where('status', 'pending')->count();
            $parts[] = "Хүлээгдэж буй чөлөө/амралтын бүртгэл: {$count}.";
        }

        if (str_contains($q, 'үүрэг') || str_contains($q, 'даалгавар')) {
            $open = Task::query()->where('progress', '<', 100)->count();
            $parts[] = "Дуусаагүй үүрэг даалгавар: {$open}.";
        }

        if (str_contains($q, 'журам')) {
            $n = Regulation::query()->count();
            $parts[] = "Бүртгэлтэй журам: {$n}.";
            $latest = Regulation::query()->latest('id')->value('title');
            if ($latest) {
                $parts[] = "Сүүлийн журам: «{$latest}».";
            }
        }

        if (str_contains($q, 'захирамж') || str_contains($q, 'тушаал')) {
            $n = Decree::query()->count();
            $parts[] = "Захирамж/тушаалын бүртгэл: {$n}.";
        }

        if (str_contains($q, 'төлөвлөгөө')) {
            $n = Plan::query()->where('status', 'active')->count();
            $parts[] = "Идэвхтэй төлөвлөгөө: {$n}.";
        }

        if ($parts === []) {
            return "Би зөвхөн энэ системийн өгөгдөл дээр ажиллана. Жишээ: «хүлээгдэж буй чөлөө», «үүрэг», «журам», «захирамж», «төлөвлөгөө» гэж асууна уу.";
        }

        return implode(' ', $parts);
    }
}
