<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * «Зөвшөөрсөн» хэсгийн тушаал ТЭК засдаг байснаас бүтэн хэсгийг
     * (тушаал + нэр хамт) нэг доороо засдаг болгож байгаа тул баганыг
     * дахин нэрлэнэ. Шинэ боломж тул одоогийн утгыг (туршилтын өгөгдөл)
     * хоослоно — алдагдах бодит мэдээлэл алга.
     */
    public function up(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->renameColumn('approver_title_override', 'approver_block_override');
        });

        DB::table('annual_leaves')->update(['approver_block_override' => null]);
    }

    public function down(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->renameColumn('approver_block_override', 'approver_title_override');
        });
    }
};
