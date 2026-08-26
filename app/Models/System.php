<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class System extends Model
{
    use HasFactory;

    public const LOGIN_MANUAL = 'manual';

    public const LOGIN_FORM_POST = 'form_post';

    /** ДАН — Үндэсний танилт, нэвтрэлтийн систем. */
    public const AUTH_DAN = 'dan';

    public const AUTH_PASSWORD = 'password';

    protected $fillable = [
        'slug',
        'name',
        'url',
        'login_url',
        'login_method',
        'login_form_action',
        'login_username_field',
        'login_password_field',
        'login_extra_fields',
        'supports_dan',
        'dan_login_url',
        'requires_login',
        'description',
        'category',
        'icon',
        'is_active',
        'is_internal',
        'sort_order',
        'is_embeddable',
        'embed_blocked_by',
        'embed_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_internal' => 'boolean',
            'requires_login' => 'boolean',
            'supports_dan' => 'boolean',
            'is_embeddable' => 'boolean',
            'embed_checked_at' => 'datetime',
            'login_extra_fields' => 'array',
        ];
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(UserCredential::class);
    }

    /**
     * Тухайн системийг харах эрхтэй албан хаагчид.
     * Хоосон байвал бүх хэрэглэгчидэд нээлттэй.
     */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Тухайн хэрэглэгчийн харах боломжтой системүүд.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->is_admin) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->whereDoesntHave('viewers');

            if ($user) {
                $q->orWhereHas('viewers', fn (Builder $v) => $v->whereKey($user->id));
            }
        });
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($user?->is_admin) {
            return true;
        }

        if ($this->viewers()->doesntExist()) {
            return true;
        }

        return (bool) $user && $this->viewers()->whereKey($user->id)->exists();
    }

    /**
     * The URL a user should be sent to when they click "Нэвтрэх".
     */
    public function entryUrl(): string
    {
        return $this->login_url ?: $this->url;
    }

    /**
     * Нуугдмал маягтаар шууд нэвтрүүлэх тохиргоо бүрэн эсэх.
     */
    /** ДАН-аар нэвтрэх эхлэх хаяг — тусад нь заагаагүй бол ердийн нэвтрэх хаяг. */
    public function danEntryUrl(): string
    {
        return $this->dan_login_url ?: $this->entryUrl();
    }

    public function canAutoSubmit(): bool
    {
        return $this->login_method === self::LOGIN_FORM_POST
            && filled($this->login_form_action)
            && filled($this->login_username_field)
            && filled($this->login_password_field);
    }
}
