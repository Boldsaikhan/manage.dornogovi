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
    public static function phone(): string
    {
        return (string) User::normalizePhone((string) config('root_admin.phone'));
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
        $email = trim((string) config('root_admin.email'));
        $name = trim((string) config('root_admin.name'));

        $user = self::find()
            ?? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

        if (! $user) {
            // User загварт «hashed» cast байгаа тул ЦЭВЭР нууц үг дамжуулна.
            return User::query()->create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password ?? (string) config('root_admin.password'),
                'email_verified_at' => now(),
                'is_admin' => true,
            ]);
        }

        $user->forceFill([
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
