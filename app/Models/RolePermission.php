<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ролийн загвар — «Мэргэжилтэн», «Хэлтсийн дарга» гэх мэт түвшин бүрд
 * ямар модульд ямар эрх өгөхийг урьдчилан тодорхойлно.
 */
class RolePermission extends Model
{
    public const ROLES = [
        'super_admin' => 'Супер админ',
        'department_head' => 'Хэлтсийн дарга',
        'specialist' => 'Мэргэжилтэн',
    ];

    /** Хэрэглэгчийн талбар ↔ роль. */
    public const ROLE_FIELDS = [
        'super_admin' => 'is_admin',
        'department_head' => 'is_department_head',
        'specialist' => 'is_specialist',
    ];

    /**
     * Тохиргоо хийгээгүй үед хэрэглэх анхны загвар.
     *
     * @var array<string, array<string, string>>
     */
    public const DEFAULTS = [
        'super_admin' => [
            'dept_dashboard' => 'manage', 'tasks' => 'manage', 'work_groups' => 'manage',
            'plans' => 'manage', 'meetings' => 'manage', 'reports' => 'manage',
            'leaves' => 'manage', 'assignments' => 'manage', 'phone_directory' => 'manage',
            'regulations' => 'manage', 'decrees' => 'manage', 'contracts' => 'manage',
            'archives' => 'manage', 'doc_standards' => 'manage', 'onboarding' => 'manage',
            'ai' => 'manage',
        ],
        'department_head' => [
            'dept_dashboard' => 'manage', 'tasks' => 'manage', 'work_groups' => 'manage',
            'plans' => 'manage', 'meetings' => 'manage', 'reports' => 'manage',
            'leaves' => 'manage', 'assignments' => 'manage', 'phone_directory' => 'view',
            'regulations' => 'view', 'decrees' => 'view', 'contracts' => 'view',
            'archives' => 'view', 'doc_standards' => 'view', 'onboarding' => 'view',
            'ai' => 'manage',
        ],
        'specialist' => [
            'dept_dashboard' => 'view', 'tasks' => 'manage', 'work_groups' => 'view',
            'plans' => 'view', 'meetings' => 'view', 'reports' => 'manage',
            'leaves' => 'manage', 'assignments' => 'manage', 'phone_directory' => 'view',
            'regulations' => 'view', 'decrees' => 'view', 'contracts' => 'view',
            'archives' => 'view', 'doc_standards' => 'view', 'onboarding' => 'view',
            'ai' => 'view',
        ],
    ];

    protected $fillable = ['role', 'module_key', 'level'];

    /**
     * Бүх ролийн загварыг {роль: {модуль: түвшин}} хэлбэрээр.
     *
     * @return array<string, array<string, string>>
     */
    public static function map(): array
    {
        $stored = array_fill_keys(array_keys(self::ROLES), []);
        $configured = [];

        foreach (static::query()->get(['role', 'module_key', 'level']) as $row) {
            if (! array_key_exists($row->role, $stored)) {
                continue;
            }

            $stored[$row->role][$row->module_key] = $row->level;
            $configured[$row->role] = true;
        }

        // Тухайн ролийг хараахан тохируулаагүй бол анхны загварыг хэрэглэнэ.
        foreach ($stored as $role => $permissions) {
            if (! isset($configured[$role])) {
                $stored[$role] = self::DEFAULTS[$role] ?? [];
            }
        }

        return $stored;
    }

    /**
     * Нэг ролийн загварыг бүхэлд нь солино.
     *
     * @param  array<string, string>  $permissions
     */
    public static function replaceFor(string $role, array $permissions): void
    {
        static::query()->where('role', $role)->delete();

        // Бүх модулийг хаасан бол «тохируулсан» гэдгийг тэмдэглэх мөр үлдээнэ.
        if (array_filter($permissions, fn ($l) => in_array($l, ['view', 'manage'], true)) === []) {
            static::create(['role' => $role, 'module_key' => '__none__', 'level' => 'view']);

            return;
        }

        foreach ($permissions as $module => $level) {
            if (! in_array($level, ['view', 'manage'], true)) {
                continue;
            }

            static::create(['role' => $role, 'module_key' => $module, 'level' => $level]);
        }
    }
}
