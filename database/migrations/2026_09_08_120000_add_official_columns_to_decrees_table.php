<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Захирамж/тушаалын бүртгэлийг албан ёсны маягтад тааруулна.
 *
 * Нэмэгдэх багана:
 *   6  Дагаж мөрдөх он, сар, өдөр
 *   9  Баримт бичгийн эх хувийн шинж
 *   10 ХХНЖ-ын хэргийн индекс
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decrees', function (Blueprint $table) {
            $table->date('effective_on')->nullable()->after('page_count');
            $table->string('original_form', 255)->nullable()->after('attachment_pages');
            $table->string('file_index', 100)->nullable()->after('original_form');
        });
    }

    public function down(): void
    {
        Schema::table('decrees', function (Blueprint $table) {
            $table->dropColumn(['effective_on', 'original_form', 'file_index']);
        });
    }
};
