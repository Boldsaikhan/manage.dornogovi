<?php

namespace Tests;

use App\Models\Role;
use App\Support\AssignmentSheet;
use App\Services\Ai\AiSettings;
use App\Support\ModuleOrder;
use App\Support\ModuleVisibility;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Модуль болон түүний бүх дэд хэсгийг нэг түвшинд нээнэ.
     *
     * Дэд хэсэг нь тохируулаагүй бол хаалттай тул тестэд эцэг мөрийг
     * нээхэд дэд мөрүүд нь ч нээгдэх ёстой үед үүнийг хэрэглэнэ.
     *
     * @return list<array{module_key: string, level: string}>
     */
    protected function moduleWithSubs(string $module, string $level): array
    {
        $rows = [['module_key' => $module, 'level' => $level]];

        foreach (array_keys(\App\Support\ModuleAccess::SUB_MODULES[$module] ?? []) as $sub) {
            $rows[] = ['module_key' => $module.':'.$sub, 'level' => $level];
        }

        return $rows;
    }

    /**
     * Эцэг модулийн түвшинг дэд хэсгүүдэд нь тарааж өгнө.
     *
     * Тодорхой зааж өгсөн дэд хэсгийг хэвээр үлдээнэ.
     *
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    protected function expandPermissions(array $map): array
    {
        $out = [];

        foreach ($map as $key => $level) {
            $out[$key] = $level;

            foreach (array_keys(\App\Support\ModuleAccess::SUB_MODULES[$key] ?? []) as $sub) {
                $subKey = $key.':'.$sub;

                if (! array_key_exists($subKey, $map)) {
                    $out[$subKey] = $level;
                }
            }
        }

        return $out;
    }

    /** Хэрэглэгчид модуль болон дэд хэсгүүдийг нь нээнэ. */
    protected function grantModule(\App\Models\User $user, string $module, string $level): void
    {
        foreach ($this->moduleWithSubs($module, $level) as $row) {
            \App\Models\UserModulePermission::create($row + ['user_id' => $user->id]);
        }
    }

    /**
     * Хүсэлт бүрд нэг л удаа уншихаар хийсэн түр санах ойг тест бүрийн
     * өмнө хаяна. Тестүүд нэг процесст ажилладаг тул өмнөх тестийн
     * ролийн загвар дараагийнхад үлдэхээс сэргийлнэ.
     */
    protected function setUp(): void
    {
        parent::setUp();

        AssignmentSheet::forgetSigners();
        Role::forgetOrdered();
        AiSettings::forgetDisplayName();
        ModuleVisibility::forget();
        ModuleOrder::forget();
    }
}
