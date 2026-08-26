<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * QR кодоор нэвтрэх нэг удаагийн хүсэлт.
 *
 * Компьютер QR үүсгэнэ → утсан дээрх нэвтэрсэн эрхээр уншуулж зөвшөөрнө →
 * компьютер төлвийг асуухдаа нэвтэрнэ. Токен нэг удаа ашиглагдана.
 */
class LoginQrToken extends Model
{
    /** Хүсэлт хүчинтэй байх хугацаа (секунд). */
    public const TTL_SECONDS = 120;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CONSUMED = 'consumed';

    protected $fillable = [
        'token',
        'status',
        'user_id',
        'requester_ip',
        'requester_agent',
        'session_id',
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

    /** Хугацаа нь дууссан хуучин хүсэлтүүдийг цэвэрлэнэ. */
    public static function prune(): void
    {
        static::query()->where('expires_at', '<', now()->subHour())->delete();
    }
}
