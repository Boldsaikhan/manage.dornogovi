<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'system_id',
        'username_encrypted',
        'password_encrypted',
        'note_encrypted',
        'last_used_at',
    ];

    /**
     * Encrypted columns are never serialised to the client. The plaintext is only
     * ever exposed through the dedicated reveal endpoint.
     */
    protected $hidden = [
        'username_encrypted',
        'password_encrypted',
        'note_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'username_encrypted' => 'encrypted',
            'password_encrypted' => 'encrypted',
            'note_encrypted' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }
}
