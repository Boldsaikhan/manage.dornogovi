<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Тушаал (том үсгээр) болон нэрийг (тодоор) тус тусад нь автоматаар
     * янзалж харуулахын тулд нэг чөлөөт бичвэрийн баганаас хоёр тусдаа
     * багана руу буцаана. Шинэ боломж тул одоогийн (туршилтын) утгыг
     * хоослоно — алдагдах бодит мэдээлэл алга.
     */
    public function up(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->dropColumn('approver_block_override');
            $table->string('approver_title_override')->nullable()->after('signer');
            $table->string('approver_name_override')->nullable()->after('approver_title_override');
        });
    }

    public function down(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->dropColumn(['approver_title_override', 'approver_name_override']);
            $table->text('approver_block_override')->nullable()->after('signer');
        });
    }
};
