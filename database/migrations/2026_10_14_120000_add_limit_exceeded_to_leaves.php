<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Жилийн цалинтай чөлөөний лимит хэтэрсэн мөрийг тэмдэглэнэ.
 *
 * Тэмдэглэгдсэн мөр нь «Цалингүй» болж, төрлийг нь солих боломжгүй.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leaves', 'limit_exceeded')) {
            return;
        }

        Schema::table('leaves', function (Blueprint $table) {
            $table->boolean('limit_exceeded')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leaves', 'limit_exceeded')) {
            return;
        }

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn('limit_exceeded');
        });
    }
};
