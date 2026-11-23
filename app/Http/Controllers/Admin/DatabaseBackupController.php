<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DatabaseBackup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Өгөгдлийн сангийн үзүүлэлт / нөөц — зөвхөн үндсэн админ (is_admin).
 */
class DatabaseBackupController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('Admin/DatabaseBackup', [
            'stats' => DatabaseBackup::stats(),
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $path = DatabaseBackup::createDump();
        $downloadName = basename($path);

        return response()
            ->download($path, $downloadName, [
                'Content-Type' => 'application/sql; charset=UTF-8',
            ])
            ->deleteFileAfterSend(true);
    }
}
