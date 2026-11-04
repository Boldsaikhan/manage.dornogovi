<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSP зөрчлийн мэдэгдлийг хүлээн авч логт бичнэ.
 *
 * Хатуу бодлогыг хэрэгжүүлэхээс өмнө юу эвдэрч болохыг эндээс
 * (/admin/logs) харна. Хэрэглэгчид юу ч мэдрэхгүй.
 */
class CspReportController extends Controller
{
    public function store(Request $request): Response
    {
        $report = $request->input('csp-report', []);

        if (! is_array($report) || $report === []) {
            return response()->noContent();
        }

        Log::warning('CSP зөрчил', [
            'directive' => (string) ($report['effective-directive'] ?? $report['violated-directive'] ?? ''),
            'blocked' => mb_substr((string) ($report['blocked-uri'] ?? ''), 0, 300),
            'document' => mb_substr((string) ($report['document-uri'] ?? ''), 0, 300),
        ]);

        return response()->noContent();
    }
}
