<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «Орлон гарын үсэг зурсан» одоо тогтмол 2 сонголт (acting|head) биш,
 * утасны жагсаалтаас сонгосон бодит албан хаагчийн нэр байх тул баганыг
 * өргөтгөнө.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->string('signer', 100)->nullable()->default(null)->change();
        });

        // Хуучин утга нь бодит нэр биш тул хоослоно — гараар дахин сонгоно.
        DB::table('leaves')->whereIn('signer', ['acting', 'head'])->update(['signer' => null]);
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->string('signer', 16)->nullable(false)->default('acting')->change();
        });
    }
};
