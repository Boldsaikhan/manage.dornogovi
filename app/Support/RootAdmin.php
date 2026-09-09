<?php

namespace App\Support;

use App\Models\User;

/**
 * Үндсэн супер админ — устгагдахгүй, эрх нь хасагдахгүй бүртгэл.
 *
 * Бүх эрх санамсаргүй хасагдсан, эсвэл админ хүн ажлаас гарсан ч системд
 * эргэж орох баталгаатай арга үлдээх зорилготой.
 */
class RootAdmin
{
    /**
     * Тохиргоо уншигдаагүй үед ч ажиллах суурь утгууд.
     *
     * Deploy дээр migration нь `config:cache`-ээс өмнө ажилладаг тул хуучин
     * кэшэнд `root_admin.*` байхгүй байж болно. Тэр үед хоосон бүртгэл
     * үүсэхээс сэргийлж эдгээрийг ашиглана.
     */
    public const DEFAULTS = [
        'phone' => '77231111',
        'email' => 'admin@dornogovi.gov.mn',
        'name' => 'Үндсэн супер админ',
        'password' => 'ZDTG@77231111',
    ];

    private static function setting(string $key): string
    {
        $value = trim((string) config('root_admin.'.$key));

        return $value !== '' ? $value : self::DEFAULTS[$key];
    }

    public static function phone(): string
    {
        return (string) User::normalizePhone(self::setting('phone'));
    }

    public static function is(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return User::normalizePhone($user->phone) === self::phone();
    }

    public static function find(): ?User
    {
        return User::query()
            ->whereNotNull('phone')
            ->get()
            ->first(fn (User $u) => User::normalizePhone($u->phone) === self::phone());
    }

    /**
     * Бүртгэлийг үүсгэх, эсвэл байгаа бол баталгаажуулна.
     *
     * @param  string|null  $password  Өгвөл нууц үгийг шинэчилнэ.
     */
    public static function ensure(?string $password = null): User
    {
        $phone = self::phone();
        $email = self::setting('email');
        $name = self::setting('name');

        $user = self::find()
            ?? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

        if (! $user) {
            // User загварт «hashed» cast байгаа тул ЦЭВЭР нууц үг дамжуулна.
            return User::query()->create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password ?? self::setting('password'),
                'email_verified_at' => now(),
                'is_admin' => true,
            ]);
        }

        // Нэр, и-мэйл нь ямар нэг шалтгаанаар хоосон үлдсэн бол нөхнө.
        $user->forceFill([
            'name' => trim((string) $user->name) !== '' ? $user->name : $name,
            'email' => trim((string) $user->email) !== '' ? $user->email : $email,
            'phone' => $phone,
            'is_admin' => true,
        ]);

        if ($password !== null) {
            $user->forceFill(['password' => $password]);
        }

        $user->save();

        return $user;
    }
}
