<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сан хугацаагаар хаагдахаа больж, гараар нээж хаадаг болов.
 *
 * Нээлттэй байсан саныг шинэ баганад хөрвүүлж авна — хэн нэгний сан
 * санамсаргүй хаагдахгүй.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('vault_opened_at')->nullable()->after('vault_opened_by');
        });

        DB::table('users')
            ->whereNotNull('vault_open_until')
            ->where('vault_open_until', '>', now())
            ->update(['vault_opened_at' => now()]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['vault_open_until', 'vault_open_hours']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('vault_open_until')->nullable()->after('vault_opened_by');
            $table->unsignedSmallInteger('vault_open_hours')->nullable()->after('vault_open_until');
        });

        DB::table('users')
            ->whereNotNull('vault_opened_at')
            ->update([
                'vault_open_until' => now()->addDays(30),
                'vault_open_hours' => 720,
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('vault_opened_at');
        });
    }
};
