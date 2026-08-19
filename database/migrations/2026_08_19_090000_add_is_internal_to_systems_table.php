<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Дотоод ажлын систем эсэх — хажуугийн цэсэнд "Дотоод ажил" бүлэгт тусад нь
     * харагдана (гадны төрийн системүүд "Нэвтрэх" бүлэгт үлдэнэ).
     */
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });
    }
};
