<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PhoneVerification;
use App\Models\User;
use App\Services\Sms\SmsSender;
use App\Services\Verify\VerifyMnClient;
use App\Services\Verify\VerifySettings;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

/**
 * Утасны дугаараар нууц үг сэргээх — verify.mn (MO SMS).
 *
 * Урсгал:
 *   1. Хэрэглэгч утасны дугаараа оруулна → verify.mn дээр session үүснэ.
 *   2. Хэрэглэгч ӨӨРӨӨ 144773 дугаар руу кодоо SMS-ээр илгээнэ.
 *   3. Хуудас 3 секунд тутам төлвийг асууна (verify.mn callback ч бас сэрээнэ).
 *   4. VERIFIED болмогц шинэ нууц үгээ тавина.
 *
 * verify.mn унтраалттай үед нөөц суваг руу шилжинэ: кодыг бид SMS-ээр илгээж,
 * хэрэглэгч хуудсан дээр бичнэ.
 *
 * Бүртгэл байгаа эсэхийг хариунаас нь мэдэх боломжгүй — алхмууд ижил явна.
 */
class PhonePasswordResetController extends Controller
{
    private const PURPOSE = 'password_reset';

    private const PENDING_KEY = 'password_reset.phone_verification_id';

    public function __construct(
        private VerifyMnClient $verify,
        private VerifySettings $settings,
    ) {}

    /**
     * Хуудасны одоогийн алхам.
     *
     * @return array<string, mixed>
     */
    public static function state(Request $request): array
    {
        $record = self::pending($request);

        if (! $record) {
            return [
                'step' => 'phone',
                'phone' => null,
                'channel' => null,
                'sms_uri' => null,
                'instruction' => null,
                'expires_at' => null,
                'shortcode' => app(VerifySettings::class)->shortcode(),
                'sms_cost' => (int) config('verify.sms_cost', 150),
            ];
        }

        return [
            'step' => $record->isVerified() ? 'password' : 'code',
            'phone' => $record->phone,
            'channel' => $record->channel,
            'sms_uri' => $record->sms_uri,
            'instruction' => $record->instruction,
            'expires_at' => optional($record->expires_at)?->toIso8601String(),
            'shortcode' => app(VerifySettings::class)->shortcode(),
            'sms_cost' => (int) config('verify.sms_cost', 150),
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
     * 1-р алхам — дугаараа өгөх, session үүсгэх.
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

        /*
         * Суваг огт тохируулаагүй бол хэн ч ирсэн ижил алдаа — бүртгэлтэй
         * эсэхийг задруулахгүй.
         */
        if (! $this->verify->isEnabled() && ! app(SmsSender::class)->isEnabled()) {
            throw ValidationException::withMessages([
                'phone' => 'Одоогоор утсаар сэргээх боломжгүй байна. И-мэйлээр сэргээх, '
                    .'эсвэл системийн админд хандана уу.',
            ]);
        }

        $user = User::query()->where('phone', $phone)->first();

        /*
         * Бүртгэлгүй дугаарыг шууд хэлнэ.
         *
         * Урьд нь «бүртгэлтэй бол илгээлээ» гээд кодын нүд рүү оруулдаг
         * байсан нь хүмүүсийг төөрөгдүүлж, ирэхгүй код хүлээлгэдэг байв.
         */
        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => 'Энэ дугаар системд бүртгэлгүй байна. '
                    .'Утасны жагсаалтад бүртгэлтэй дугаараа оруулах, эсвэл системийн админд хандана уу.',
            ]);
        }

        $code = $this->verify->generateCode();

        $record = PhoneVerification::query()->create([
            'phone' => $phone,
            'purpose' => self::PURPOSE,
            'channel' => 'sms',
            'code_hash' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes((int) config('verify.code_ttl', 5)),
            'ip' => $request->ip(),
        ]);

        $result = $this->verify->startVerification($phone, $code);

        /*
         * Код хаашаа ч очоогүй бол оруулах нүд харуулах нь утгагүй.
         * (verify.mn тохируулаагүй, нөөц SMS суваг ч унтраалттай.)
         */
        if (! $result['sent']) {
            $record->delete();

            throw ValidationException::withMessages([
                'phone' => 'Одоогоор утсаар сэргээх боломжгүй байна. И-мэйлээр сэргээх, '
                    .'эсвэл системийн админд хандана уу.',
            ]);
        }

        $record->update(array_filter([
            'channel' => $result['channel'],
            'session_id' => $result['session_id'],
            'sms_uri' => $result['sms_uri'],
            'instruction' => $result['instruction'],
            'expires_at' => $result['expires_at']
                ? Carbon::parse($result['expires_at'])
                : $record->expires_at,
        ]));

        PhoneVerification::prune();

        $request->session()->put(self::PENDING_KEY, $record->id);

        return back()->with('status', $record->channel === 'verify.mn'
            ? 'Доорх зааврын дагуу кодоо илгээнэ үү.'
            : 'Баталгаажуулах код илгээлээ.');
    }

    /**
     * Нөөц сувгийн алхам — кодыг гараар бичих.
     *
     * verify.mn сувагт код нь хэрэглэгчийн ИЛГЭЭХ текст тул хуудсан дээр
     * бичих нь юуг ч нотлохгүй — тэр сувагт энэ арга хаалттай.
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

        if ($record->channel === 'verify.mn') {
            throw ValidationException::withMessages([
                'code' => 'Кодоо '.$this->settings->shortcode().' дугаар руу SMS-ээр илгээнэ үү.',
            ]);
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
     * Хуудас 3 секунд тутам энэ хаягаар төлвийг асууна.
     *
     * verify.mn сувагт АЛБАН ЁСНЫ төлвийг нь эх сурвалжаас нь шалгана —
     * callback ирсэн эсэхээс үл хамааран.
     */
    public function status(Request $request): JsonResponse
    {
        $record = self::pending($request);

        if (! $record) {
            return response()->json(['verified' => false, 'expired' => true]);
        }

        if (! $record->isVerified() && $record->channel === 'verify.mn' && $record->session_id) {
            $status = $this->verify->sessionStatus($record->session_id);

            if ($status === 'VERIFIED') {
                $record->update(['verified_at' => Carbon::now()]);
            } elseif ($status === 'EXPIRED') {
                $record->update(['expires_at' => Carbon::now()->subSecond()]);

                return response()->json(['verified' => false, 'expired' => true]);
            }
        }

        return response()->json([
            'verified' => $record->isVerified(),
            'expired' => false,
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
        $request->session()->forget(self::PENDING_KEY);

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
        $request->session()->forget(self::PENDING_KEY);

        return back();
    }

    /**
     * verify.mn-ээс ирэх «шалгаарай» дохио.
     *
     * GET хүсэлт, бие ч, гарын үсэг ч байхгүй. Хурдан 2xx буцаах ёстой тул
     * зөвхөн хүлээж буй session-уудын төлвийг эх сурвалжаас нь шалгана.
     */
    public function callback(Request $request, string $secret): Response
    {
        $expected = $this->settings->callbackSecret();

        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);

        PhoneVerification::query()
            ->where('purpose', self::PURPOSE)
            ->where('channel', 'verify.mn')
            ->whereNotNull('session_id')
            ->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->each(function (PhoneVerification $record): void {
                if ($this->verify->sessionStatus((string) $record->session_id) === 'VERIFIED') {
                    $record->update(['verified_at' => Carbon::now()]);
                }
            });

        return response('OK', 200);
    }
}
