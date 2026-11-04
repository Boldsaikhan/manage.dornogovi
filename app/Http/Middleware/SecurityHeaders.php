<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Хөтөч талын хамгаалалтын толгойнууд.
 *
 * Эдгээрийг nginx дээр ч тавьдаг ч certbot тохиргоог дарж бичих үед
 * алга болдог тул аппаас өөрөөс нь илгээнэ — хоёр давхар хамгаалалт.
 *
 * CSP-г хоёр хэсэгт хуваасан:
 *   1. Хэрэгжүүлэх (enforce) — эвдрэх магадлалгүй, халдлагаас шууд
 *      хамгаалах заалтууд (iframe-д хийх, base/object тарих, маягтыг
 *      хулгайлах).
 *   2. Зөвхөн мэдээлэх (report-only) — эх үүсвэрийн хатуу хязгаар.
 *      Эвдэрч болзошгүй тул эхлээд зөрчлийг логт бичиж ажиглана.
 */
class SecurityHeaders
{
    /** Фонт татдаг цорын ганц гадаад эх үүсвэр. */
    private const FONTS = 'https://fonts.bunny.net';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        // Хуудсыг өөр сайтын iframe-д оруулахыг хориглоно (clickjacking).
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Браузер файлын төрлийг таамаглахыг хориглоно.
        $headers->set('X-Content-Type-Options', 'nosniff');
        // Гадагш очих хаягт бүтэн замыг задруулахгүй.
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Хэрэггүй төхөөрөмжийн эрхийг урьдчилан хаана.
        $headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), payment=(), usb=(), interest-cohort=()',
        );
        // Flash/PDF-ийн хуучин cross-domain бодлогыг хориглоно.
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        /*
         * Цонхны тусгаарлалт. Холбосон систем рүү window.open-оор ордог
         * тул popup-ийг зөвшөөрсөн хувилбарыг сонгов.
         */
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        if ($this->isHtml($response)) {
            $headers->set('Content-Security-Policy', $this->enforced());
            $headers->set('Content-Security-Policy-Report-Only', $this->reportOnly());
        }

        return $response;
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    /**
     * Эвдрэх магадлалгүй, шууд хэрэгжих заалтууд.
     */
    private function enforced(): string
    {
        return implode('; ', [
            // <base> тарьж бүх холбоосыг хулгайлахыг хориглоно.
            "base-uri 'self'",
            // Flash, Java applet мэтийг бүрэн хориглоно.
            "object-src 'none'",
            // Өөр сайт бидний хуудсыг iframe-д оруулахгүй.
            "frame-ancestors 'self'",
            /*
             * Маягтыг өөр хаяг руу илгээхийг хязгаарлана. Холбосон систем
             * рүү нэвтрэхэд гадаад https хаяг руу илгээдэг тул https-ийг
             * зөвшөөрнө — харин http, data:, javascript: хаалттай.
             */
            "form-action 'self' https:",
            // Холимог агуулгыг (http) автоматаар https болгоно.
            'upgrade-insecure-requests',
        ]);
    }

    /**
     * Хатуу хувилбар — одоохондоо зөвхөн зөрчлийг мэдээлнэ.
     */
    private function reportOnly(): string
    {
        $fonts = self::FONTS;

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' {$fonts}",
            "font-src 'self' data: {$fonts}",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "media-src 'self' data: blob:",
            "worker-src 'self' blob:",
            "frame-src 'self'",
            "manifest-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self' https:",
            'report-uri /csp-report',
        ]);
    }
}
