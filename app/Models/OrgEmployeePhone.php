<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgEmployeePhone extends Model
{
    /** АЗДТГ нэгжийн төрөл. */
    public const UNIT_TYPES = [
        'heltes' => 'Хэлтэс',
    ];

    protected $fillable = [
        'organization',
        'unit',
        'unit_type',
        'position',
        'last_name',
        'first_name',
        'room',
        'work_phone',
        'mobile_phone',
        'email',
        'sort_order',
    ];

    public static function guessUnitType(?string $unit): ?string
    {
        $name = mb_strtolower(trim((string) $unit));

        if ($name === '') {
            return null;
        }

        if (str_contains($name, 'хэлтэс') || str_contains($name, 'хэллтсийн')) {
            return 'heltes';
        }

        return null;
    }
}
