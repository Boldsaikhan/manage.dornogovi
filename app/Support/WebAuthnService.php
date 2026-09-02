<?php

namespace App\Support;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Http\Request;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use RuntimeException;

class WebAuthnService
{
    public static function make(Request $request): WebAuthn
    {
        $rpId = self::rpId($request);
        $rpName = config('app.name', 'manage дотоод систем');

        // Зөвхөн 'none' — Android/iOS passkey бүртгэл найдвартай (attestation: none).
        return new WebAuthn($rpName, $rpId, ['none'], true);
    }

    public static function rpId(Request $request): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $host;
        }

        return $request->getHost();
    }

    /**
     * WebAuthn userHandle — хамгийн багадаа 16 байт (Android зарим төхөөрөмж богино id-г татгалздаг).
     */
    public static function userHandle(User $user): string
    {
        return hash('sha256', 'dornogovi-webauthn-'.$user->id, true);
    }

    public static function userIdFromHandle(?string $handle): ?int
    {
        // Хуучин 4/8 байт handle + шинэ sha256 — credential.user_id-аар баталгаажуулна.
        if ($handle === null || $handle === '') {
            return null;
        }

        if (strlen($handle) === 4) {
            $parts = unpack('Nid', $handle);

            return isset($parts['id']) ? (int) $parts['id'] : null;
        }

        if (strlen($handle) === 8) {
            $parts = unpack('Jid', $handle);

            return isset($parts['id']) ? (int) $parts['id'] : null;
        }

        return null;
    }

    public static function b64urlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $value): string
    {
        $pad = 4 - (strlen($value) % 4);
        if ($pad < 4) {
            $value .= str_repeat('=', $pad);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Буруу base64url өгөгдөл.');
        }

        return $decoded;
    }

    /**
     * @return array{publicKey: object}
     */
    public static function registrationOptions(Request $request, User $user): array
    {
        $webauthn = self::make($request);

        $exclude = $user->webauthnCredentials()
            ->pluck('credential_id')
            ->map(fn (string $id) => self::b64urlDecode($id))
            ->all();

        // required: утсан дээрх passkey нэвтрэхэд олдохоор (discoverable).
        $args = $webauthn->getCreateArgs(
            self::userHandle($user),
            $user->email ?: ($user->phone ?: 'user-'.$user->id),
            $user->name ?: 'Хэрэглэгч',
            60,
            'required',
            'required',
            false,         // platform only
            $exclude
        );

        $challenge = $webauthn->getChallenge();
        $request->session()->put('webauthn.challenge', self::b64urlEncode(
            $challenge instanceof ByteBuffer ? $challenge->getBinaryString() : (string) $challenge
        ));
        $request->session()->put('webauthn.user_id', $user->id);

        return json_decode(json_encode($args), true);
    }

    public static function register(Request $request, User $user, array $payload): WebAuthnCredential
    {
        $webauthn = self::make($request);
        $challengeB64 = $request->session()->pull('webauthn.challenge');
        $sessionUserId = $request->session()->pull('webauthn.user_id');

        if (! $challengeB64 || (int) $sessionUserId !== (int) $user->id) {
            throw new RuntimeException('Бүртгэлийн сесс дууссан. Дахин оролдоно уу.');
        }

        $clientDataJSON = self::b64urlDecode($payload['clientDataJSON'] ?? '');
        $attestationObject = self::b64urlDecode($payload['attestationObject'] ?? '');
        $challenge = self::b64urlDecode($challengeB64);

        try {
            // requireUserVerification=false — preferred бүртгэлтэй нийцнэ
            $data = $webauthn->processCreate(
                $clientDataJSON,
                $attestationObject,
                $challenge,
                false,
                true,
                false
            );
        } catch (WebAuthnException $e) {
            throw new RuntimeException('Биометрик бүртгэл амжилтгүй: '.$e->getMessage(), 0, $e);
        }

        $credentialId = self::b64urlEncode($data->credentialId);

        return WebAuthnCredential::query()->updateOrCreate(
            ['credential_id' => $credentialId],
            [
                'user_id' => $user->id,
                'public_key' => $data->credentialPublicKey,
                'sign_count' => (int) ($data->signatureCounter ?? 0),
                'aaguid' => isset($data->AAGUID) ? bin2hex($data->AAGUID) : null,
                'device_name' => $payload['device_name'] ?? self::guessDeviceName($request),
            ]
        );
    }

    /**
     * @return array{publicKey: object}
     */
    public static function loginOptions(Request $request): array
    {
        $login = trim((string) $request->input('login', ''));

        if ($login === '') {
            throw new RuntimeException('Утасны дугаараа оруулна уу.');
        }

        $user = User::findByLogin($login);

        if (! $user) {
            throw new RuntimeException('Хэрэглэгч олдсонгүй. Дугаараа шалгаад дахин оролдоно уу.');
        }

        if (! $user->webauthnCredentials()->exists()) {
            throw new RuntimeException('Энэ утсанд хуруу/царай бүртгэгдээгүй. Эхлээд нууц үгээр нэвтэрч, «Идэвхжүүлэх» дарна уу.');
        }

        $request->session()->put('webauthn.expected_user_id', $user->id);

        $webauthn = self::make($request);

        // Хоосон allowCredentials — төхөөрөмж дээрх passkey-г браузер өөрөө олно.
        $args = $webauthn->getGetArgs(
            [],
            120,
            false,
            false,
            false,
            false, // hybrid
            true,  // internal = finger / face
            'preferred'
        );

        $challenge = $webauthn->getChallenge();
        $request->session()->put('webauthn.challenge', self::b64urlEncode(
            $challenge instanceof ByteBuffer ? $challenge->getBinaryString() : (string) $challenge
        ));

        return json_decode(json_encode($args), true);
    }

    /**
     * Нэвтэрсэн хэрэглэгчийн бүртгэлтэй credential-уудаар assertion options.
     *
     * @return array{publicKey: object}
     */
    public static function assertionOptionsForUser(Request $request, User $user): array
    {
        if (! $user->webauthnCredentials()->exists()) {
            throw new RuntimeException('Биометрик бүртгэл олдсонгүй.');
        }

        $request->session()->put('webauthn.expected_user_id', $user->id);

        $webauthn = self::make($request);

        // Төхөөрөмж дээрх passkey-г браузер өөрөө олно (discoverable).
        $args = $webauthn->getGetArgs(
            [],
            60,
            false,
            false,
            false,
            false,
            true,
            'preferred'
        );

        $challenge = $webauthn->getChallenge();
        $request->session()->put('webauthn.challenge', self::b64urlEncode(
            $challenge instanceof ByteBuffer ? $challenge->getBinaryString() : (string) $challenge
        ));

        return json_decode(json_encode($args), true);
    }

    public static function authenticate(Request $request, array $payload): User
    {
        $credential = self::assertCredential($request, $payload);

        $user = $credential->user;
        if (! $user) {
            throw new RuntimeException('Хэрэглэгч олдсонгүй.');
        }

        return $user;
    }

    /** Нэвтэрсэн хэрэглэгчийн биометрикийг баталгаажуулна. */
    public static function verifyForUser(Request $request, User $user, array $payload): void
    {
        $credential = self::assertCredential($request, $payload);

        if ((int) $credential->user_id !== (int) $user->id) {
            throw new RuntimeException('Биометрик энэ хэрэглэгчид хамаарахгүй байна.');
        }
    }

    private static function assertCredential(Request $request, array $payload): WebAuthnCredential
    {
        $webauthn = self::make($request);
        $challengeB64 = $request->session()->pull('webauthn.challenge');
        $expectedUserId = $request->session()->pull('webauthn.expected_user_id');

        if (! $challengeB64) {
            throw new RuntimeException('Нэвтрэх сесс дууссан. Дахин оролдоно уу.');
        }

        $id = self::b64urlDecode($payload['id'] ?? ($payload['rawId'] ?? ''));
        $credentialId = self::b64urlEncode($id);

        $credential = WebAuthnCredential::query()
            ->where('credential_id', $credentialId)
            ->first();

        if (! $credential) {
            throw new RuntimeException('Энэ утсанд хуруу/царай бүртгэгдээгүй. Эхлээд нууц үгээр нэвтэрч, «Идэвхжүүлэх» дарна уу.');
        }

        if ($expectedUserId && (int) $credential->user_id !== (int) $expectedUserId) {
            throw new RuntimeException('Оруулсан дугаарын биометрик бүртгэл таарахгүй байна.');
        }

        $clientDataJSON = self::b64urlDecode($payload['clientDataJSON'] ?? '');
        $authenticatorData = self::b64urlDecode($payload['authenticatorData'] ?? '');
        $signature = self::b64urlDecode($payload['signature'] ?? '');
        $userHandle = ! empty($payload['userHandle'])
            ? self::b64urlDecode($payload['userHandle'])
            : null;

        if ($userHandle !== null) {
            $handleUserId = self::userIdFromHandle($userHandle);
            if ($handleUserId && $handleUserId !== (int) $credential->user_id) {
                throw new RuntimeException('Хэрэглэгчийн мэдээлэл таарахгүй байна.');
            }
        }

        try {
            $webauthn->processGet(
                $clientDataJSON,
                $authenticatorData,
                $signature,
                $credential->public_key,
                self::b64urlDecode($challengeB64),
                $credential->sign_count > 0 ? $credential->sign_count : null,
                true
            );
        } catch (WebAuthnException $e) {
            throw new RuntimeException('Биометрик нэвтрэлт амжилтгүй: '.$e->getMessage(), 0, $e);
        }

        $newCounter = $webauthn->getSignatureCounter();
        if (is_int($newCounter) && $newCounter > 0) {
            $credential->update(['sign_count' => $newCounter]);
        }

        return $credential;
    }

    public static function guessDeviceName(Request $request): string
    {
        $ua = strtolower($request->userAgent() ?? '');

        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) {
            return 'iPhone / iPad (Face ID / Touch ID)';
        }
        if (str_contains($ua, 'android')) {
            return 'Android (хуруу / нүүр)';
        }
        if (str_contains($ua, 'windows')) {
            return 'Windows Hello';
        }
        if (str_contains($ua, 'mac')) {
            return 'Mac (Touch ID)';
        }

        return 'Энэ төхөөрөмж';
    }
}
