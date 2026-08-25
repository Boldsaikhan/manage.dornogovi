<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Хүснэгтэн бүртгэлд гарчгийг хоосон үлдээж болно.
        Schema::table('decrees', function (Blueprint $table) {
            $table->string('title', 1000)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('decrees', function (Blueprint $table) {
            $table->string('title', 1000)->nullable(false)->default('')->change();
        });
    }
};
