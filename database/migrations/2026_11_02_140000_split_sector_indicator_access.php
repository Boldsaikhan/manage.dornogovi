<?php

use App\Models\RolePermission;
use App\Models\UserModulePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Салбарын үзүүлэлт өөрийн эрхтэй болно.
 *
 * Өмнө нь салбарын хуулийн эрхийг дагадаг байсан тул тэр түвшинг
 * хуулж, хандалт гэнэт хаагдахаас сэргийлнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        UserModulePermission::query()
            ->where('module_key', 'legal_acts')
            ->orderBy('id')
            ->each(function (UserModulePermission $row): void {
                UserModulePermission::query()->firstOrCreate(
                    ['user_id' => $row->user_id, 'module_key' => 'sector_indicators'],
                    ['level' => $row->level],
                );
            });

        RolePermission::query()
            ->where('module_key', 'legal_acts')
            ->orderBy('id')
            ->each(function (RolePermission $row): void {
                RolePermission::query()->firstOrCreate(
                    ['role' => $row->role, 'module_key' => 'sector_indicators'],
                    ['level' => $row->level],
                );
            });

        RolePermission::forgetMap();
    }

    public function down(): void
    {
        // Хуулсан эрхийг буцааж устгахгүй — гараар өөрчилсөн байж болно.
    }
};
