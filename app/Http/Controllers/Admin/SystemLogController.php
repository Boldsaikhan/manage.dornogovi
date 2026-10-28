<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SystemLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Системийн алдааны бүртгэл — зөвхөн админд.
 */
class SystemLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('Admin/SystemLogs', [
            'entries' => SystemLog::recent(40),
            'logPath' => SystemLog::path(),
        ]);
    }
}
