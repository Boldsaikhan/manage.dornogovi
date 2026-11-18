<?php

use App\Console\Commands\ImportStateHighAwards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Нэг удаа: 2024 оны төрийн дээд шагналын бүртгэлийг
 * ОДОН-БҮРТГЭЛ-2024_1.xlsx-ээс бэлдсэн JSON-оор солино.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('awards')) {
            return;
        }

        $path = database_path('data/state_high_awards_2024.json');

        if (! is_file($path)) {
            return;
        }

        Artisan::call(ImportStateHighAwards::class, [
            '--file' => $path,
            '--year' => 2024,
            '--replace-year' => true,
        ]);
    }

    public function down(): void
    {
        // Өгөгдөл солисон — буцаах боломжгүй.
    }
};
