<?php

namespace App\Services\Sms;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Нэвтрэх мэдээллийг утасны дугаар луу SMS-ээр илгээнэ.
 *
 * SMS_DRIVER=log — хөгжүүлэлт (log файл)
 * SMS_DRIVER=http — гадны REST API (token + JSON)
 */
class SmsSender
{
    public function isEnabled(): bool
    {
        return (bool) config('sms.enabled');
    }

    public function send(?string $phone, string $message): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $to = $this->formatPhoneForApi($phone);

        if ($to === null || trim($message) === '') {
            return false;
        }

        return $this->deliver($to, trim($message));
    }

    public function sendLoginCredentials(User $user, string $plainPassword): bool
    {
        if ($user->phone === null || $user->phone === '') {
            return false;
        }

        return $this->send(
            $user->phone,
            $this->buildLoginMessage($user, $plainPassword),
        );
    }

    public function buildLoginMessage(User $user, string $plainPassword): string
    {
        $template = config('sms.login_message') ?: config('sms.login_message_default');

        $replacements = [
            '{app}' => (string) config('app.name'),
            '{url}' => rtrim((string) config('app.url'), '/'),
            '{phone}' => (string) $user->phone,
            '{password}' => $plainPassword,
            '{name}' => (string) $user->name,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public function formatPhoneForApi(?string $phone): ?string
    {
        $digits = User::normalizePhone($phone);

        if ($digits === null || strlen($digits) !== 8) {
            return null;
        }

        $prefix = preg_replace('/\D+/', '', (string) config('sms.phone_prefix', '976')) ?? '976';

        return $prefix.$digits;
    }

    protected function deliver(string $to, string $message): bool
    {
        $driver = (string) config('sms.driver', 'log');

        try {
            return match ($driver) {
                'http' => $this->deliverHttp($to, $message),
                default => $this->deliverLog($to, $message),
            };
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    protected function deliverLog(string $to, string $message): bool
    {
        Log::info('SMS (log driver)', [
            'to' => $to,
            'from' => config('sms.from'),
            'body' => $message,
        ]);

        return true;
    }

    protected function deliverHttp(string $to, string $message): bool
    {
        $url = trim((string) config('sms.http.url'));

        if ($url === '') {
            Log::warning('SMS http driver: SMS_HTTP_URL тохируулаагүй байна.');

            return false;
        }

        $phoneField = (string) config('sms.http.phone_field', 'phone');
        $messageField = (string) config('sms.http.message_field', 'message');
        $method = strtolower((string) config('sms.http.method', 'POST'));
        $timeout = (int) config('sms.http.timeout', 15);

        $request = Http::timeout($timeout);

        $token = trim((string) config('sms.http.token'));

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $payload = [
            $phoneField => $to,
            $messageField => $message,
        ];

        $from = trim((string) config('sms.from'));

        if ($from !== '') {
            $payload['from'] = $from;
        }

        $response = $method === 'get'
            ? $request->get($url, $payload)
            : $request->post($url, $payload);

        if ($response->successful()) {
            return true;
        }

        Log::warning('SMS http driver: амжилтгүй хариу', [
            'to' => $to,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }
}
