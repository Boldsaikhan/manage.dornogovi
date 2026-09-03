<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LoginCredentialsMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Нэвтрэх мэдээллээ мартсан үед — бүртгэлтэй и-мэйл рүү илгээнэ.
 *
 * Хуучин нууц үгийг буцааж уншиж болдоггүй (hash) тул шинэ түр нууц үг үүсгэж,
 * нэвтрэх нэрийн хамт илгээнэ.
 */
class PasswordResetLinkController extends Controller
{
    /** Түр нууц үгийн урт. */
    private const PASSWORD_LENGTH = 10;

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [], ['email' => 'и-мэйл хаяг']);

        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->first();

        // Бүртгэл байгаа эсэхийг задруулахгүй — хариу үргэлж ижил.
        $status = 'Хэрэв энэ хаяг бүртгэлтэй бол нэвтрэх мэдээллийг илгээлээ. Ирсэн захиагаа шалгана уу.';

        if (! $user) {
            return back()->with('status', $status);
        }

        $password = self::temporaryPassword();

        $user->forceFill(['password' => Hash::make($password)])->save();

        try {
            Mail::to($user->email)->send(new LoginCredentialsMail($user, $password));
        } catch (Throwable $e) {
            Log::error('Нэвтрэх мэдээлэл илгээж чадсангүй.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return back()->with('status', $status);
    }

    /**
     * Уншихад ойлгомжтой түр нууц үг — андуурч болзошгүй тэмдэгтгүй.
     */
    private static function temporaryPassword(): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // I, O байхгүй
        $lower = 'abcdefghijkmnpqrstuvwxyz';   // l, o байхгүй
        $digits = '23456789';                  // 0, 1 байхгүй
        $pool = $upper.$lower.$digits;

        // Том, жижиг үсэг, тоо тус бүр заавал орно.
        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];

        for ($i = count($chars); $i < self::PASSWORD_LENGTH; $i++) {
            $chars[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        shuffle($chars);

        return implode('', $chars);
    }
}
