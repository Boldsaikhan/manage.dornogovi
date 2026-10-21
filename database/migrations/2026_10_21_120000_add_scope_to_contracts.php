<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Гэрээг баталсан албан тушаалтнаар нь ангилна.
 *
 * Албан ёсны бүртгэл «Засаг дарга», «Тамгын газрын дарга» гэсэн
 * хэсгүүдэд хуваагддаг.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('contracts', 'scope')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->string('scope', 32)->default('zasag_darga')->after('id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('contracts', 'scope')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
