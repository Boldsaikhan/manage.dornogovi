<?php

use App\Console\Commands\RepairAwardNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Нэг удаа: шагналын нэрийн CP1251 mojibake засвар + давхардал нэгтгэл.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('award_types') || ! Schema::hasTable('awards')) {
            return;
        }

        Artisan::call(RepairAwardNames::class);
    }

    public function down(): void
    {
        // Өгөгдлийн засвар — буцаах боломжгүй.
    }
};
