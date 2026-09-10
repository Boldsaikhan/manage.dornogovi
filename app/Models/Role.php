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
        return static::$orderedCache ??= static::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Нэг хүсэлтийн доторх түр санах ой — эрх шалгах бүрд дахин уншихгүй.
     *
     * @var \Illuminate\Database\Eloquent\Collection<int, static>|null
     */
    private static ?Collection $orderedCache = null;

    public static function forgetOrdered(): void
    {
        static::$orderedCache = null;
        RolePermission::forgetMap();
    }

    /** Бүртгэлтэй ролийн түлхүүрүүд. */
    public static function keys(): array
    {
        return static::ordered()->pluck('key')->all();
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetOrdered());
        static::deleted(fn () => static::forgetOrdered());
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
