<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * QR кодоор нэвтрэх / vault нээх нэг удаагийн хүсэлт.
 *
 * Компьютер QR үүсгэнэ → утсан дээрх нэвтэрсэн эрхээр уншуулж зөвшөөрнө →
 * компьютер төлвийг асууж нэвтэрнэ эсвэл сан нээнэ. Токен нэг удаа ашиглагдана.
 */
class LoginQrToken extends Model
{
    /** Хүсэлт хүчинтэй байх хугацаа (секунд). */
    public const TTL_SECONDS = 120;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CONSUMED = 'consumed';

    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_VAULT = 'vault_unlock';

    protected $fillable = [
        'token',
        'status',
        'purpose',
        'user_id',
        'expected_user_id',
        'requester_ip',
        'requester_agent',
        'session_id',
        'client_secret_hash',
        'approved_at',
        'consumed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'consumed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expected_user_id');
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    /** Утсан дээр зөвшөөрөх боломжтой эсэх. */
    public function isActionable(): bool
    {
        return $this->status === self::PENDING && ! $this->isExpired();
    }

    public function isVaultUnlock(): bool
    {
        return $this->purpose === self::PURPOSE_VAULT;
    }

    /** Хугацаа нь дууссан хуучин хүсэлтүүдийг цэвэрлэнэ. */
    public static function prune(): void
    {
        static::query()->where('expires_at', '<', now()->subHour())->delete();
    }
}
