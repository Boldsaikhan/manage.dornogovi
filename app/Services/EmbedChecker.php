<?php

namespace App\Services;

use App\Models\System;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Тухайн систем iframe дотор нээгдэх боломжтой эсэхийг сервер талаас шалгана.
 *
 * Хоёр толгойн мэдээлэл үүнийг хориглодог:
 *   X-Frame-Options: DENY | SAMEORIGIN
 *   Content-Security-Policy: frame-ancestors …
 */
class EmbedChecker
{
    /**
     * @return array{embeddable: bool|null, blocked_by: string|null}
     */
    public function check(string $url): array
    {
        try {
            $response = Http::withoutVerifying()
                ->timeout(20)
                ->withHeaders(['User-Agent' => 'ManageDornogovi/1.0 embed-check'])
                ->get($url);
        } catch (Throwable $e) {
            return ['embeddable' => null, 'blocked_by' => 'Холбогдож чадсангүй: '.$e->getMessage()];
        }

        $xfo = $this->header($response->headers(), 'x-frame-options');

        if ($xfo !== null && preg_match('/deny|sameorigin|allow-from/i', $xfo)) {
            return ['embeddable' => false, 'blocked_by' => 'X-Frame-Options: '.trim($xfo)];
        }

        foreach ($this->all($response->headers(), 'content-security-policy') as $csp) {
            if (preg_match('/frame-ancestors\s+([^;]+)/i', $csp, $m)) {
                $value = trim($m[1]);

                // Зөвхөн * байвал хэн ч суулгаж болно.
                if (! preg_match('/(^|\s)\*(\s|$)/', $value)) {
                    return ['embeddable' => false, 'blocked_by' => 'CSP frame-ancestors: '.$value];
                }
            }
        }

        return ['embeddable' => true, 'blocked_by' => null];
    }

    public function refresh(System $system): System
    {
        $result = $this->check($system->entryUrl());

        $system->forceFill([
            'is_embeddable' => $result['embeddable'],
            'embed_blocked_by' => $result['blocked_by'],
            'embed_checked_at' => now(),
        ])->save();

        return $system;
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     */
    private function header(array $headers, string $name): ?string
    {
        return $this->all($headers, $name)[0] ?? null;
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @return array<int, string>
     */
    private function all(array $headers, string $name): array
    {
        foreach ($headers as $key => $values) {
            if (strtolower($key) === $name) {
                return array_values((array) $values);
            }
        }

        return [];
    }
}
