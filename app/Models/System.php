<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class System extends Model
{
    use HasFactory;

    public const LOGIN_MANUAL = 'manual';

    public const LOGIN_FORM_POST = 'form_post';

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
     * The URL a user should be sent to when they click "Нэвтрэх".
     */
    public function entryUrl(): string
    {
        return $this->login_url ?: $this->url;
    }

    /**
     * Нуугдмал маягтаар шууд нэвтрүүлэх тохиргоо бүрэн эсэх.
     */
    public function canAutoSubmit(): bool
    {
        return $this->login_method === self::LOGIN_FORM_POST
            && filled($this->login_form_action)
            && filled($this->login_username_field)
            && filled($this->login_password_field);
    }
}
