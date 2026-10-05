<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * «Шинэ нэмэх» дарахад хоосон мөр үүсгээд, нүд нүдээр нь бөглөдөг тул
     * гарчгийг заавал биш болгоно.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
