<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            URL::forceRootUrl($appUrl);

            if (str_starts_with(parse_url($appUrl, PHP_URL_SCHEME) ?: '', 'https')) {
                URL::forceScheme('https');
            }
        }
    }
}
