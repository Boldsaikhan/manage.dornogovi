<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Утасны дугаараар нэвтрэх боломж нэмнэ.
     *
     * Дугаарыг зөвхөн цифрээр (8 орон) хадгална — оруулах үед хэлбэржүүлэлт,
     * зай, зураас зэргийг App\Models\User::normalizePhone() цэвэрлэнэ.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
        });
    }
};
