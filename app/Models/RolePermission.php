<?php

namespace App\Models;

use App\Support\ModuleAccess;
use Illuminate\Database\Eloquent\Model;

/**
 * Ролийн загвар — «Мэргэжилтэн», «Хэлтсийн дарга» гэх мэт түвшин бүрд
 * ямар модульд ямар эрх өгөхийг урьдчилан тодорхойлно.
 */
class RolePermission extends Model
{
    /** Хэрэглэгчийн талбар ↔ суурь роль. */
    public const ROLE_FIELDS = Role::SYSTEM_FIELDS;

    /**
     * Бүртгэлтэй бүх роль: {түлхүүр: нэр}.
     *
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return Role::ordered()->pluck('label', 'key')->all();
    }

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
            'awards' => 'manage',
            'regulations' => 'manage', 'decrees' => 'manage', 'contracts' => 'manage',
            'archives' => 'manage', 'doc_standards' => 'manage', 'onboarding' => 'manage',
            'ai' => 'manage',
        ],
        'department_head' => [
            'dept_dashboard' => 'manage', 'tasks' => 'manage', 'work_groups' => 'manage',
            'plans' => 'manage', 'meetings' => 'manage', 'reports' => 'manage',
            'leaves' => 'manage', 'assignments' => 'manage', 'phone_directory' => 'view',
            'awards' => 'manage',
            'regulations' => 'view', 'decrees' => 'view', 'contracts' => 'view',
            'archives' => 'view', 'doc_standards' => 'view', 'onboarding' => 'view',
            'ai' => 'manage',
        ],
        'specialist' => [
            'dept_dashboard' => 'view', 'tasks' => 'manage', 'work_groups' => 'view',
            'plans' => 'view', 'meetings' => 'view', 'reports' => 'manage',
            'leaves' => 'manage', 'assignments' => 'manage', 'phone_directory' => 'view',
            'awards' => 'manage',
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
        $stored = array_fill_keys(array_keys(self::roles()), []);
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
        if (array_filter($permissions, fn ($l) => in_array($l, ModuleAccess::LEVELS, true)) === []) {
            static::create(['role' => $role, 'module_key' => '__none__', 'level' => 'view']);

            return;
        }

        foreach ($permissions as $module => $level) {
            if (! in_array($level, ModuleAccess::LEVELS, true)) {
                continue;
            }

            static::create(['role' => $role, 'module_key' => $module, 'level' => $level]);
        }
    }
}
