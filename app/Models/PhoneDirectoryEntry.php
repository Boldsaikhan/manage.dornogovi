<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneDirectoryEntry extends Model
{
    /** Чөлөөний бүртгэлийн хамрах хүрээтэй нийцсэн ангилал. */
    public const CATEGORIES = [
        'agentlag' => 'Агентлаг',
        'sum' => 'Сумд',
        'baiguullaga' => 'Байгууллага',
    ];

    protected $fillable = [
        'org_name', 'category', 'org_order', 'sort_order', 'person_name', 'position',
        'office_phone', 'mobile_phone',
    ];

    /**
     * Байгууллагын нэрээр ангиллыг таамаглана — дараа нь гараар засаж болно.
     */
    public static function guessCategory(?string $orgName): string
    {
        $name = mb_strtolower((string) $orgName);

        if (str_contains($name, 'сум')) {
            return 'sum';
        }

        if (str_contains($name, 'агентлаг')) {
            return 'agentlag';
        }

        return 'baiguullaga';
    }
}
