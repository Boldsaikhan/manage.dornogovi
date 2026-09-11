<?php

use App\Support\ModuleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Өвлөж байсан дэд эрхийг тодорхой бичнэ.
 *
 * Урьд нь дэд мөрийг (Захирамж А, Татах гэх мэт) тохируулаагүй бол эцэг
 * модулийнхоо эрхийг дагадаг байв. Одооноос тохируулаагүй бол ХААЛТТАЙ
 * болж байгаа тул одоо байгаа роль, хэрэглэгчдийн бодит эрх өөрчлөгдөхөөс
 * сэргийлж, тэдний өнөөдөр эдэлж байгаа түвшнийг мөр болгон хадгална.
 */
return new class extends Migration
{
    public function up(): void
    {
        $subKeys = [];

        foreach (ModuleAccess::SUB_MODULES as $parent => $subs) {
            foreach (array_keys($subs) as $sub) {
                $subKeys[$parent][] = $parent.':'.$sub;
            }
        }

        $this->fill('role_permissions', 'role', $subKeys);
        $this->fill('user_module_permissions', 'user_id', $subKeys);
    }

    public function down(): void
    {
        // Буцаахгүй — тодорхой бичсэн эрхийг арилгавал хандалт өөрчлөгдөнө.
    }

    /**
     * @param  array<string, list<string>>  $subKeys
     */
    private function fill(string $table, string $ownerColumn, array $subKeys): void
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->get([$ownerColumn, 'module_key', 'level']);

        $byOwner = [];

        foreach ($rows as $row) {
            $byOwner[$row->{$ownerColumn}][$row->module_key] = $row->level;
        }

        $insert = [];

        foreach ($byOwner as $owner => $levels) {
            foreach ($subKeys as $parent => $keys) {
                $parentLevel = $levels[$parent] ?? null;

                // Эцэг нь хаалттай байсан бол дэд нь ч хаалттай — мөр хэрэггүй.
                if ($parentLevel === null || $parentLevel === ModuleAccess::LEVEL_CLOSED) {
                    continue;
                }

                foreach ($keys as $key) {
                    if (array_key_exists($key, $levels)) {
                        continue;
                    }

                    $insert[] = [
                        $ownerColumn => $owner,
                        'module_key' => $key,
                        'level' => $parentLevel,
                    ];
                }
            }
        }

        foreach (array_chunk($insert, 200) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
};
