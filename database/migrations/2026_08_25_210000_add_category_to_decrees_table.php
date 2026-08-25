<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decrees', function (Blueprint $table) {
            // blank = бланкны дугаар, decree = захирамж/тушаалын дугаар
            $table->string('category', 32)->default('decree')->after('id');
        });

        DB::table('decrees')->whereNull('category')->orWhere('category', '')->update(['category' => 'decree']);
    }

    public function down(): void
    {
        Schema::table('decrees', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
