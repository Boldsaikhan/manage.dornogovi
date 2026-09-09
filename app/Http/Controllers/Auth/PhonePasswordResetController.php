<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PhoneVerification;
use App\Models\User;
use App\Services\Verify\VerifyMnClient;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

/**
 * Утасны дугаараар нууц үг сэргээх.
 *
 * 1. Хэрэглэгч утасны дугаараа оруулна → verify.mn-ээр нэг удаагийн код очно.
 * 2. Кодоо хуудсан дээр бичнэ (эсвэл verify.mn callback-аар баталгаажина).
 * 3. Шинэ нууц үгээ өөрөө тавина.
 *
 * Бүртгэл байгаа эсэхийг хариунаас нь мэдэх боломжгүй — алхмууд ижил явна.
 */
class PhonePasswordResetController extends Controller
{
    private const PURPOSE = 'password_reset';

    /** Session-д хадгалах түлхүүрүүд. */
    private const PENDING_KEY = 'password_reset.phone_verification_id';

    public function __construct(private VerifyMnClient $verify) {}

    /**
     * Хуудасны одоогийн алхам — сэргээх хуудас үүнийг ашиглана.
     *
     * @return array{step: string, phone: ?string, channel: ?string}
     */
    public static function state(Request $request): array
    {
        $record = self::pending($request);

        if (! $record) {
            return ['step' => 'phone', 'phone' => null, 'channel' => null];
        }

        return [
            'step' => $record->isVerified() ? 'password' : 'code',
            'phone' => $record->phone,
            'channel' => $request->session()->get('password_reset.channel'),
        ];
    }

    /** Хүчинтэй, ашиглагдаагүй мөрийг session-оос олно. */
    private static function pending(Request $request): ?PhoneVerification
    {
        $id = $request->session()->get(self::PENDING_KEY);

        if (! $id) {
            return null;
        }

        $record = PhoneVerification::query()->find($id);

        if (! $record || $record->purpose !== self::PURPOSE || ! $record->isUsable()) {
            return null;
        }

        return $record;
    }

    /**
     * 1-р алхам — дугаараа өгөх, код илгээх.
     */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ], [], ['phone' => 'утасны дугаар']);

        $phone = User::normalizePhone($data['phone']);

        if ($phone === null || strlen($phone) !== 8) {
            throw ValidationException::withMessages([
                'phone' => 'Утасны дугаараа 8 оронгоор бичнэ үү.',
            ]);
        }

        $code = $this->verify->generateCode();

        // Бүртгэлгүй дугаар байсан ч алхам ижил үргэлжилнэ — задруулахгүй.
        $user = User::query()->where('phone', $phone)->first();

        $record = PhoneVerification::query()->create([
            'phone' => $phone,
            'purpose' => self::PURPOSE,
            'code_hash' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes((int) config('verify.code_ttl', 10)),
            'ip' => $request->ip(),
        ]);

        $channel = null;

        if ($user) {
            $result = $this->verify->sendCode($phone, $code);
            $channel = $result['channel'];

            if ($result['session_id']) {
                $record->update(['session_id' => $result['session_id']]);
            }
        }

        PhoneVerification::prune();

        $request->session()->put(self::PENDING_KEY, $record->id);
        $request->session()->put('password_reset.channel', $channel);

        return back()->with(
            'status',
            'Хэрэв энэ дугаар бүртгэлтэй бол баталгаажуулах код илгээлээ.',
        );
    }

    /**
     * 2-р алхам — кодыг шалгах.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ], [], ['code' => 'код']);

        $record = self::pending($request);

        if (! $record) {
            throw ValidationException::withMessages([
                'code' => 'Кодын хугацаа дууссан байна. Дугаараа дахин оруулна уу.',
            ]);
        }

        if ($record->isVerified()) {
            return back();
        }

        $max = (int) config('verify.max_attempts', 5);

        if ($record->attempts >= $max) {
            throw ValidationException::withMessages([
                'code' => 'Хэт олон удаа буруу оруулсан байна. Кодоо дахин авна уу.',
            ]);
        }

        $code = preg_replace('/\D+/', '', $data['code']) ?? '';

        if ($code === '' || ! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            throw ValidationException::withMessages(['code' => 'Код таарахгүй байна.']);
        }

        $record->update(['verified_at' => Carbon::now()]);

        return back();
    }

    /**
     * verify.mn callback-аар баталгаажсан эсэхийг хуудас шалгана.
     */
    public function status(Request $request): JsonResponse
    {
        $record = self::pending($request);

        return response()->json([
            'verified' => (bool) $record?->isVerified(),
            'expired' => $record === null,
        ]);
    }

    /**
     * 3-р алхам — шинэ нууц үг тавих.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [], ['password' => 'нууц үг']);

        $record = self::pending($request);

        if (! $record || ! $record->isVerified()) {
            throw ValidationException::withMessages([
                'password' => 'Баталгаажуулалт хүчингүй болсон байна. Эхнээс нь дахин оролдоно уу.',
            ]);
        }

        $user = User::query()->where('phone', $record->phone)->first();

        $record->update(['consumed_at' => Carbon::now()]);
        $request->session()->forget([self::PENDING_KEY, 'password_reset.channel']);

        if ($user) {
            $user->forceFill([
                'password' => $data['password'],
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        }

        return redirect()->route('login')->with(
            'status',
            'Нууц үг шинэчлэгдлээ. Шинэ нууц үгээрээ нэвтэрнэ үү.',
        );
    }

    /** Дахин эхлэх. */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget([self::PENDING_KEY, 'password_reset.channel']);

        return back();
    }

    /**
     * verify.mn-ээс ирэх мэдэгдэл.
     *
     * Session id-гаар, эсвэл дугаар + кодоор нь тааруулж баталгаажуулна.
     */
    public function callback(Request $request, string $secret): JsonResponse
    {
        $expected = trim((string) config('verify.callback_secret'));

        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);

        $sessionId = (string) ($request->input('id')
            ?? $request->input('sessionId')
            ?? $request->input('session_id')
            ?? '');

        $phone = User::normalizePhone((string) $request->input('phone', ''));
        $code = preg_replace('/\D+/', '', (string) $request->input('text', $request->input('code', ''))) ?? '';

        $query = PhoneVerification::query()
            ->where('purpose', self::PURPOSE)
            ->whereNull('consumed_at')
            ->whereNull('verified_at')
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('id');

        if ($sessionId !== '') {
            $record = (clone $query)->where('session_id', $sessionId)->first();
        } else {
            $record = null;
        }

        if (! $record && $phone !== null) {
            $record = (clone $query)->where('phone', $phone)->first();

            // Дугаараар олдсон бол кодыг нь бас шалгана.
            if ($record && $code !== '' && ! Hash::check($code, $record->code_hash)) {
                $record = null;
            }
        }

        if (! $record) {
            return response()->json(['ok' => false], 404);
        }

        $record->update(['verified_at' => Carbon::now()]);

        return response()->json(['ok' => true]);
    }
}
