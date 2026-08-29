<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * www.manage… → manage… (DNS www A/CNAME шаардлагатай — deploy/dns-request-email.md).
 */
class ForceNonWww
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if (str_starts_with($host, 'www.')) {
            $apex = substr($host, 4);
            $target = $request->getScheme().'://'.$apex.$request->getRequestUri();

            return redirect()->away($target, 301);
        }

        return $next($request);
    }
}
