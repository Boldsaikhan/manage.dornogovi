<?php

namespace App\Http\Controllers;

use App\Models\EditUndo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Сүүлийн үйлдлийг буцаах, дахин хийх.
 */
class UndoController extends Controller
{
    /** Буцаах. */
    public function store(Request $request): RedirectResponse
    {
        return $this->apply($request, EditUndo::UNDO, 'Буцаах үйлдэл алга.', 'Буцаалаа');
    }

    /** Дахин хийх. */
    public function redo(Request $request): RedirectResponse
    {
        return $this->apply($request, EditUndo::REDO, 'Дахин хийх үйлдэл алга.', 'Дахин хийлээ');
    }

    private function apply(Request $request, string $kind, string $empty, string $done): RedirectResponse
    {
        $entry = EditUndo::latestFor($request->user(), $kind);

        if (! $entry) {
            return back(303)->with('success', $empty);
        }

        $summary = $entry->summary;
        $reverted = $entry->revert();

        return back(303)->with(
            'success',
            $reverted
                ? $done.($summary ? ': '.$summary : '.')
                : 'Тухайн бүртгэл олдсонгүй — түүхээс хаслаа.',
        );
    }
}
