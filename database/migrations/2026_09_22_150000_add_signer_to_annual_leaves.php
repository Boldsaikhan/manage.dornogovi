<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Зөвшөөрсөн» — ээлжийн амралтыг батласан удирдах албан тушаалтан.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->string('signer', 100)->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->dropColumn('signer');
        });
    }
};
