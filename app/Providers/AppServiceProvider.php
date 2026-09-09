<?php

namespace App\Providers;

use App\Support\Vault;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
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

        // Нэг нэвтэрсэн бол «Гарах» дартал нэвтэрсэн хэвээр байна. Сесс богино
        // байвал гар утсан дээр байн байн нэвтрэх шаардлагатай болдог тул доод
        // хязгаарыг энд тавина (.env-ийн SESSION_LIFETIME үүнээс бага байсан ч).
        $minimum = max(1, (int) config('session.min_days', 30)) * 24 * 60;

        if ((int) config('session.lifetime') < $minimum) {
            config(['session.lifetime' => $minimum]);
        }

        // Нэвтэрмэгц нэвтрэх мэдээллийн санг нээнэ — цэснээс холбосон систем дээр
        // дарахад нэмэлт нууц үг асуухгүй шууд орно.
        Event::listen(Login::class, static fn () => Vault::unlockCurrentSession());

        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            URL::forceRootUrl($appUrl);

            if (str_starts_with(parse_url($appUrl, PHP_URL_SCHEME) ?: '', 'https')) {
                URL::forceScheme('https');
            }
        }
    }
}
