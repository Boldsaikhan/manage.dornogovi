<?php

namespace App\Http\Controllers;

use App\Models\EditUndo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Сүүлийн үйлдлийг буцаах.
 */
class UndoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $entry = EditUndo::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->first();

        if (! $entry) {
            return back(303)->with('success', 'Буцаах үйлдэл алга.');
        }

        $summary = $entry->summary;
        $reverted = $entry->revert();

        return back(303)->with(
            'success',
            $reverted
                ? 'Буцаалаа'.($summary ? ': '.$summary : '.')
                : 'Тухайн бүртгэл олдсонгүй — түүхээс хаслаа.',
        );
    }
}
