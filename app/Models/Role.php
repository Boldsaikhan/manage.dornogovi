<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Хандах эрхийн түвшин. Суурь 3 роль системийнх (устгах боломжгүй),
 * бусдыг админ өөрөө нэмж, загварыг нь тохируулна.
 */
class Role extends Model
{
    /** Суурь ролиуд ↔ хэрэглэгчийн талбар. */
    public const SYSTEM_FIELDS = [
        'super_admin' => 'is_admin',
        'department_head' => 'is_department_head',
        'specialist' => 'is_specialist',
    ];

    protected $fillable = ['key', 'label', 'is_system', 'sort_order'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /** @return Collection<int, Role> */
    public static function ordered(): Collection
    {
        return static::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function permissions()
    {
        return $this->hasMany(RolePermission::class, 'role', 'key');
    }

    /** Нэрнээс давхардахгүй түлхүүр үүсгэнэ. */
    public static function keyFor(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'role';
        $key = $base;
        $i = 2;

        while (static::query()->where('key', $key)->exists()) {
            $key = $base.'_'.$i;
            $i++;
        }

        return $key;
    }
}
