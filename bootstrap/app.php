<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\EnsurePwaBiometricLock::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'module' => \App\Http\Middleware\EnsureModuleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // iPhone/Safari дээр CSRF тасрахад 419 модал биш, нэвтрэх хуудас руу буцаана.
        $exceptions->respond(function ($response, $e, Request $request) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            $message = 'Холболтын хугацаа дууссан тул дахин оролдоно уу.';

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $message], 419);
            }

            if (! $request->user()) {
                return redirect()->route('login', [], 303)->with('status', $message);
            }

            return back(303)->with('status', $message);
        });

        // Устгагдсан мөр дээр засвар/устгал илгээхэд 404 биш, эелдэг мэдэгдэл.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $isMissingRow = $e->getPrevious() instanceof ModelNotFoundException;

            // Зөвхөн хуудсан дээрх засвар (Inertia) — API/JSON хүсэлтэд 404 хэвээр.
            if (! $isMissingRow || $request->isMethod('GET') || ! $request->header('X-Inertia')) {
                return null;
            }

            return back(303)->with(
                'success',
                'Тухайн мөр аль хэдийн устсан байна — жагсаалтыг шинэчиллээ.',
            );
        });
    })->create();
