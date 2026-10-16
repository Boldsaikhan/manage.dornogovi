<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Чөлөөний бүртгэлд «Хэлтэс» баганыг тусад нь хадгална.
 *
 * Өмнө нь байгууллага, хэлтэс хоёр нэг нүдэнд байсан.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leaves', 'unit_name')) {
            return;
        }

        Schema::table('leaves', function (Blueprint $table) {
            $table->string('unit_name')->nullable()->after('org_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leaves', 'unit_name')) {
            return;
        }

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn('unit_name');
        });
    }
};
