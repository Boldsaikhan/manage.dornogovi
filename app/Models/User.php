<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Утасны дугаарыг зөвхөн цифр болгож цэвэрлэнэ.
     *
     * Хэрэглэгч "9911 1234", "+976 9911-1234", "976 99111234" гэх мэтээр
     * бичсэн ч бүгд "99111234" болж нэгдэнэ. Хоосон бол null буцаана.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // Монголын улсын код (976) урдаа бичигдсэн бол хасна.
        if (strlen($digits) === 11 && str_starts_with($digits, '976')) {
            $digits = substr($digits, 3);
        }

        return $digits === '' ? null : $digits;
    }

    /**
     * Утасны дугаарыг үргэлж цэвэрлэсэн хэлбэрээр хадгална.
     */
    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = self::normalizePhone($value);
    }
}
