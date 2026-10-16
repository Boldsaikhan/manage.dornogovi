<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Чөлөөний мөр бүр журмын аль заалтаар олгогдсоныг хадгална.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leaves', 'rule_code')) {
            return;
        }

        Schema::table('leaves', function (Blueprint $table) {
            $table->string('rule_code', 20)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leaves', 'rule_code')) {
            return;
        }

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn('rule_code');
        });
    }
};
