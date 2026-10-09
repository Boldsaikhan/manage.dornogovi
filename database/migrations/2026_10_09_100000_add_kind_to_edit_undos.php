<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Дахин хийх» (redo) боломж.
 *
 * Нэг хүснэгтэд хоёр стек хадгална: kind = undo (буцаах), redo (дахин
 * хийх). Одоо байгаа бүх мөр нь буцаах стекийнх тул анхны утга «undo».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('edit_undos', 'kind')) {
            return;
        }

        Schema::table('edit_undos', function (Blueprint $table) {
            $table->string('kind', 8)->default('undo')->after('user_id');
            $table->index(['user_id', 'kind', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('edit_undos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'kind', 'id']);
            $table->dropColumn('kind');
        });
    }
};
