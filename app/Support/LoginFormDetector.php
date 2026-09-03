<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Системийн нэвтрэх хуудсыг уншиж, маягтын тохиргоог таамаглана.
 *
 * Ингэснээр `form_post` горимыг гараар бөглөхгүй — сервер өөрөө маягтын хаяг,
 * талбарын нэрсийг олж өгнө. Энэ горим нь өргөтгөлгүй, гар утсан дээр ч
 * ажилладаг тул холбосон систем рүү нэг товшилтоор нэвтэрнэ.
 */
class LoginFormDetector
{
    /** Динамик байх магадлалтай нууц талбарууд. */
    private const DYNAMIC_NAMES = '/csrf|token|nonce|state|_key|authenticity/i';

    /**
     * @return array{
     *     ok: bool,
     *     reason?: string,
     *     action?: string,
     *     method?: string,
     *     username_field?: string,
     *     password_field?: string,
     *     extra_fields?: array<string, string>,
     *     dynamic_fields?: list<string>,
     * }
     */
    public function detect(string $loginUrl): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; ManageDornogovi/1.0)',
                'Accept' => 'text/html,application/xhtml+xml',
            ])->withoutVerifying()->timeout(12)->get($loginUrl);
        } catch (Throwable $e) {
            return ['ok' => false, 'reason' => 'Хуудсыг нээж чадсангүй: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'reason' => 'Хуудас '.$response->status().' хариу өглөө.'];
        }

        $finalUrl = (string) ($response->effectiveUri() ?? $loginUrl);
        $form = $this->findLoginForm($response->body());

        if (! $form) {
            return [
                'ok' => false,
                'reason' => 'Нууц үгийн талбартай маягт олдсонгүй. Энэ систем JavaScript-ээр нэвтэрдэг байж магадгүй.',
            ];
        }

        $method = strtoupper(trim($form->getAttribute('method'))) ?: 'GET';

        if ($method !== 'POST') {
            return ['ok' => false, 'reason' => 'Маягт нь POST биш («'.$method.'») тул энэ горимд тохирохгүй.'];
        }

        $fields = $this->fields($form);

        if (! $fields['password']) {
            return ['ok' => false, 'reason' => 'Нууц үгийн талбарын нэр олдсонгүй.'];
        }

        if (! $fields['username']) {
            return ['ok' => false, 'reason' => 'Нэвтрэх нэрийн талбар олдсонгүй.'];
        }

        return [
            'ok' => true,
            'action' => $this->absoluteUrl($form->getAttribute('action'), $finalUrl),
            'method' => 'POST',
            'username_field' => $fields['username'],
            'password_field' => $fields['password'],
            'extra_fields' => $fields['hidden'],
            'dynamic_fields' => $fields['dynamic'],
        ];
    }

    private function findLoginForm(string $html): ?DOMElement
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//form') as $form) {
            if (! $form instanceof DOMElement) {
                continue;
            }

            if ($xpath->query('.//input[translate(@type,"PASSWORD","password")="password"]', $form)->length > 0) {
                return $form;
            }
        }

        return null;
    }

    /**
     * @return array{username: ?string, password: ?string, hidden: array<string, string>, dynamic: list<string>}
     */
    private function fields(DOMElement $form): array
    {
        $username = null;
        $password = null;
        $hidden = [];
        $dynamic = [];
        $candidates = [];

        foreach ($form->getElementsByTagName('input') as $input) {
            $name = trim($input->getAttribute('name'));

            if ($name === '') {
                continue;
            }

            $type = strtolower(trim($input->getAttribute('type'))) ?: 'text';

            if ($type === 'password') {
                $password ??= $name;

                continue;
            }

            if ($type === 'hidden') {
                $value = $input->getAttribute('value');
                $hidden[$name] = $value;

                // Урт санамсаргүй утга — хүсэлт бүрт шинэчлэгддэг байж магадгүй.
                if (preg_match(self::DYNAMIC_NAMES, $name) || mb_strlen($value) >= 24) {
                    $dynamic[] = $name;
                }

                continue;
            }

            if (in_array($type, ['text', 'email', 'tel', 'number'], true)) {
                // Нууц үгийн ӨМНӨХ сүүлчийн талбарыг нэвтрэх нэр гэж үзнэ.
                $candidates[] = $name;
            }
        }

        if ($password !== null) {
            $username = end($candidates) ?: null;
        }

        return [
            'username' => $username ?: null,
            'password' => $password,
            'hidden' => $hidden,
            'dynamic' => array_values(array_unique($dynamic)),
        ];
    }

    private function absoluteUrl(string $action, string $base): string
    {
        $action = trim($action);

        if ($action === '') {
            return $base;
        }

        if (preg_match('#^https?://#i', $action)) {
            return $action;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($action, '/')) {
            return $origin.$action;
        }

        $dir = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $origin.$dir.'/'.$action;
    }
}
