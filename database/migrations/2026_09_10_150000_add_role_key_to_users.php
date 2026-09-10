<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Хэрэглэгчийн ролийг шууд хадгална.
 *
 * Өмнө нь роль нь зөвхөн эрхийн жагсаалтыг таамаглан таниулдаг байсан тул
 * эрхгүй (хоосон) роль сонгоход «Рольгүй» гэж харагддаг байв.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_key', 64)->nullable()->index()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role_key');
        });
    }
};
