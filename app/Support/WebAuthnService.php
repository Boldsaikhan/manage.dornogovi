<?php

namespace App\Support;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use RuntimeException;
use Throwable;

class WebAuthnService
{
    /**
     * Challenge-ийг зөвхөн PHP сессэд найдахгүй байхын тулд шифрлэсэн,
     * бие даасан (stateless) токен болгож клиент рүү дамжуулна.
     *
     * Гар утасны PWA дээр апп дэвсгэрт очиход browser process нь OS-оор
     * хаагдаж, дараа нь дахин нээгдэхэд session cookie алга болсон байх нь
     * түгээмэл. Хуруу/царай баталгаажуулах цонх хариу авахад хэдэн секунд
     * зарцуулдаг тул энэ хугацаанд session алдагдвал "Нэвтрэх сесс дууссан"
     * гэсэн алдаа мөнхөд гардаг байв. Токен нь клиент рүү очоод буцаад
     * ирдэг тул session-оос үл хамааран баталгаажина.
     */
    private const STATE_TTL_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $data
     */
    private static function packState(array $data): ?string
    {
        try {
            return Crypt::encryptString(json_encode(
                $data + ['exp' => time() + self::STATE_TTL_SECONDS],
                JSON_THROW_ON_ERROR,
            ));
        } catch (Throwable $e) {
            // Токен бэлдэж чадаагүй ч session нөөц зам хэвээр ажиллана.
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function unpackState(mixed $token): ?array
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return null;
        }

        if (! is_array($data) || ! isset($data['exp']) || (int) $data['exp'] < time()) {
            return null;
        }

        return $data;
    }

    /**
     * unpackState() яагаад null буцаасныг оношлоход ашиглана (зөвхөн алдааны
     * лог бичихэд дуудагдана — энгийн урсгалд нөлөөлөхгүй).
     */
    private static function diagnoseState(mixed $token): string
    {
        if (! is_string($token) || $token === '') {
            return 'missing';
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return 'decrypt_failed: '.get_class($e).': '.$e->getMessage();
        }

        if (! is_array($data) || ! isset($data['exp'])) {
            return 'malformed_payload';
        }

        if ((int) $data['exp'] < time()) {
            return 'expired ('.(time() - (int) $data['exp']).'s ago)';
        }

        return 'kind_mismatch ('.($data['kind'] ?? 'null').')';
    }

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

        /*
         * required: утсан дээрх passkey нэвтрэхэд олдохоор (discoverable).
         *
         * Төрлийг нь заахгүй (null). Урьд нь зөвхөн «platform» гэж
         * шаарддаг байсан тул iPad-ын Chrome шиг өөрийн passkey сантай
         * хөтөч дээр бүртгэл үүсэхгүй байв.
         */
        $args = $webauthn->getCreateArgs(
            self::userHandle($user),
            $user->email ?: ($user->phone ?: 'user-'.$user->id),
            $user->name ?: 'Хэрэглэгч',
            60,
            'required',
            'required',
            null,
            $exclude
        );

        $challenge = $webauthn->getChallenge();
        $challengeB64 = self::b64urlEncode(
            $challenge instanceof ByteBuffer ? $challenge->getBinaryString() : (string) $challenge
        );
        $request->session()->put('webauthn.challenge', $challengeB64);
        $request->session()->put('webauthn.user_id', $user->id);

        $result = json_decode(json_encode($args), true);
        $result['state'] = self::packState([
            'kind' => 'register',
            'challenge' => $challengeB64,
            'user_id' => $user->id,
        ]);

        return $result;
    }

    public static function register(Request $request, User $user, array $payload): WebAuthnCredential
    {
        $webauthn = self::make($request);

        $state = self::unpackState($payload['state'] ?? null);

        if ($state && ($state['kind'] ?? null) === 'register' && (int) ($state['user_id'] ?? 0) === (int) $user->id) {
            $challengeB64 = (string) ($state['challenge'] ?? '');
        } else {
            $challengeB64 = $request->session()->pull('webauthn.challenge');
            $sessionUserId = $request->session()->pull('webauthn.user_id');

            if (! $challengeB64 || (int) $sessionUserId !== (int) $user->id) {
                throw new RuntimeException('Бүртгэлийн сесс дууссан. Дахин оролдоно уу.');
            }
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

        return self::assertionOptionsForUser($request, $user);
    }

    /**
     * Нэвтэрсэн хэрэглэгчийн бүртгэлтэй credential-уудаар assertion options.
     *
     * @return array{publicKey: object}
     */
    public static function assertionOptionsForUser(Request $request, User $user): array
    {
        $webauthn = self::make($request);

        $ids = $user->webauthnCredentials()
            ->get(['credential_id'])
            ->map(fn (WebAuthnCredential $c) => self::b64urlDecode($c->credential_id))
            ->filter()
            ->values()
            ->all();

        if ($ids === []) {
            throw new RuntimeException('Биометрик бүртгэл олдсонгүй.');
        }

        $request->session()->put('webauthn.expected_user_id', $user->id);

        /*
         * Энэ утсан дээрх credential ID-аар олно (discoverable биш хуучин
         * passkey-д ч ажиллана).
         *
         * Бүх төрлийн баталгаажуулагчийг зөвшөөрнө. Урьд нь зөвхөн
         * «internal» гэж хязгаарладаг байсан тул Google Password Manager,
         * iCloud Keychain-д хадгалагдсан passkey олдохгүй, хуруу уншуулсан
         * ч нээгддэггүй байв.
         */
        $args = $webauthn->getGetArgs(
            $ids,
            120,
            allowUsb: true,
            allowNfc: true,
            allowBle: true,
            allowHybrid: true,
            allowInternal: true,
            requireUserVerification: 'preferred',
        );

        $challenge = $webauthn->getChallenge();
        $challengeB64 = self::b64urlEncode(
            $challenge instanceof ByteBuffer ? $challenge->getBinaryString() : (string) $challenge
        );
        $request->session()->put('webauthn.challenge', $challengeB64);

        $result = json_decode(json_encode($args), true);
        $result['state'] = self::packState([
            'kind' => 'assert',
            'challenge' => $challengeB64,
            'expected_user_id' => $user->id,
        ]);

        return $result;
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

        $rawState = $payload['state'] ?? null;
        $state = self::unpackState($rawState);
        $sessionHadChallenge = $request->session()->has('webauthn.challenge');

        if ($state && ($state['kind'] ?? null) === 'assert') {
            $challengeB64 = (string) ($state['challenge'] ?? '');
            $expectedUserId = $state['expected_user_id'] ?? null;
        } else {
            $challengeB64 = $request->session()->pull('webauthn.challenge');
            $expectedUserId = $request->session()->pull('webauthn.expected_user_id');
        }

        if (! $challengeB64) {
            // Static шинжилгээгээр давхар шалгасан ч хэрэглэгч дээр давтагдсаар
            // байгаа тул яг энэ мөчид state яагаад унасныг лог болгож үлдээнэ.
            Log::warning('webauthn.assert.session_expired', [
                'user_id' => optional($request->user())->id,
                'user_agent' => $request->userAgent(),
                'state_present' => is_string($rawState) && $rawState !== '',
                'state_failure' => self::diagnoseState($rawState),
                'session_had_challenge' => $sessionHadChallenge,
            ]);

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
