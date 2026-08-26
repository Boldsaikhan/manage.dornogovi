<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginQrToken;
use App\Support\HomeRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * QR кодоор нэвтрэх.
 *
 *  1. Компьютер `create` дуудаж нэг удаагийн токен авна, QR болгон харуулна.
 *  2. Утсан дээрх нэвтэрсэн эрхээр QR-ыг уншиж `show` хуудсыг нээнэ.
 *  3. Хэрэглэгч зөвшөөрөхөд токен `approved` болно.
 *  4. Компьютер `status`-ыг байнга асууж байгаад зөвшөөрөгдмөгц нэвтэрнэ.
 *
 * Токен 2 минут хүчинтэй, нэг л удаа ашиглагдана.
 */
class QrLoginController extends Controller
{
    /** Компьютер: шинэ хүсэлт үүсгэнэ. */
    public function create(Request $request): JsonResponse
    {
        LoginQrToken::prune();

        // Нэг session нэг л идэвхтэй хүсэлттэй байна.
        LoginQrToken::query()
            ->where('session_id', $request->session()->getId())
            ->where('status', LoginQrToken::PENDING)
            ->update(['status' => LoginQrToken::REJECTED]);

        $token = LoginQrToken::create([
            'token' => LoginQrToken::generateToken(),
            'status' => LoginQrToken::PENDING,
            'requester_ip' => $request->ip(),
            'requester_agent' => substr((string) $request->userAgent(), 0, 500),
            'session_id' => $request->session()->getId(),
            'expires_at' => now()->addSeconds(LoginQrToken::TTL_SECONDS),
        ]);

        return response()->json([
            'token' => $token->token,
            // Утсаар уншихад нээгдэх хаяг.
            'url' => route('login.qr.show', $token->token),
            'expires_in' => LoginQrToken::TTL_SECONDS,
        ]);
    }

    /** Компьютер: төлөв асууна. Зөвшөөрөгдсөн бол энэ session нэвтэрнэ. */
    public function status(Request $request, string $token): JsonResponse
    {
        $record = LoginQrToken::where('token', $token)->first();

        if (! $record || $record->isExpired()) {
            return response()->json(['status' => 'expired']);
        }

        if ($record->status === LoginQrToken::REJECTED) {
            return response()->json(['status' => 'rejected']);
        }

        if ($record->status !== LoginQrToken::APPROVED) {
            return response()->json(['status' => 'pending']);
        }

        // Токеныг зөвхөн үүсгэсэн session хэрэглэнэ.
        if ($record->session_id !== $request->session()->getId()) {
            return response()->json(['status' => 'pending']);
        }

        $user = $record->user;

        if (! $user) {
            return response()->json(['status' => 'expired']);
        }

        // Нэг удаагийн — шууд хаана.
        $record->forceFill([
            'status' => LoginQrToken::CONSUMED,
            'consumed_at' => now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'status' => 'approved',
            'redirect' => route(HomeRedirect::routeName()),
        ]);
    }

    /** Утас: QR уншсаны дараах баталгаажуулах хуудас. */
    public function show(Request $request, string $token): Response
    {
        $record = LoginQrToken::where('token', $token)->first();

        return Inertia::render('Auth/QrApprove', [
            'token' => $token,
            'valid' => (bool) $record?->isActionable(),
            'state' => $record?->status ?? 'missing',
            'device' => $record ? [
                'ip' => $record->requester_ip,
                'agent' => self::describeAgent($record->requester_agent),
                'requested_at' => $record->created_at->diffForHumans(),
            ] : null,
        ]);
    }

    /** Утас: зөвшөөрнө. */
    public function approve(Request $request, string $token): RedirectResponse
    {
        $record = LoginQrToken::where('token', $token)->first();

        if (! $record || ! $record->isActionable()) {
            return back()->withErrors(['token' => 'Хүсэлтийн хугацаа дууссан байна. Компьютер дээрээ QR-ыг шинэчилнэ үү.']);
        }

        $record->forceFill([
            'status' => LoginQrToken::APPROVED,
            'user_id' => $request->user()->id,
            'approved_at' => now(),
        ])->save();

        return back()->with('success', 'Зөвшөөрлөө. Компьютер дээрээ нэвтэрч байна.');
    }

    /** Утас: татгалзана. */
    public function reject(Request $request, string $token): RedirectResponse
    {
        LoginQrToken::where('token', $token)
            ->where('status', LoginQrToken::PENDING)
            ->update(['status' => LoginQrToken::REJECTED]);

        return back()->with('success', 'Хүсэлтийг цуцаллаа.');
    }

    /** User-agent-ийг товч, ойлгомжтой болгоно. */
    private static function describeAgent(?string $agent): string
    {
        $agent = (string) $agent;

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Тодорхойгүй браузер',
        };

        $platform = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return trim($browser.($platform !== '' ? ' · '.$platform : ''));
    }
}
