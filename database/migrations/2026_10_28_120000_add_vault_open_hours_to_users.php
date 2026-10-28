<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Нээсэн хугацааг автоматаар сунгахад ямар урттай сунгахыг мэдэхийн тулд
 * анх нээсэн хугацааг хадгална.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('vault_open_hours')->nullable()->after('vault_opened_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('vault_open_hours');
        });
    }
};
