<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «Сонголтгүй» гэж үлдээх боломжтой болгоно.
        Schema::table('phone_directory_entries', function (Blueprint $table) {
            $table->string('category', 16)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('phone_directory_entries', function (Blueprint $table) {
            $table->string('category', 16)->nullable(false)->default('baiguullaga')->change();
        });
    }
};
