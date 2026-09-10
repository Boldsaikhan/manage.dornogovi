<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Захирамж, тушаалын хүснэгтэд индекс нэмнэ.
 *
 * Таб солих бүрд төрөл тус бүрээр тоолж, шүүдэг тул мөрийн тоо олшрохад
 * индексгүйгээр удаашрана.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decrees', function (Blueprint $table) {
            $table->index(['kind', 'number'], 'decrees_kind_number_index');
            $table->index('category', 'decrees_category_index');
        });
    }

    public function down(): void
    {
        Schema::table('decrees', function (Blueprint $table) {
            $table->dropIndex('decrees_kind_number_index');
            $table->dropIndex('decrees_category_index');
        });
    }
};
