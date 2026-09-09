<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Утасны дугаарын нэг удаагийн код.
 *
 * Кодыг задалж уншихгүй — hash-аар нь харьцуулна. verify.mn-ээс ирсэн
 * callback ч, хэрэглэгчийн гараар бичсэн код ч энэ мөрийг баталгаажуулна.
 */
class PhoneVerification extends Model
{
    protected $fillable = [
        'phone',
        'purpose',
        'channel',
        'code_hash',
        'session_id',
        'sms_uri',
        'instruction',
        'attempts',
        'verified_at',
        'consumed_at',
        'expires_at',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && ! $this->isExpired();
    }

    /** Хугацаа дууссан, ашиглагдсан мөрүүдийг цэвэрлэнэ. */
    public static function prune(): void
    {
        static::query()
            ->where('expires_at', '<', Carbon::now()->subDay())
            ->delete();
    }
}
