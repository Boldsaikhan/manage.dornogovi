<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * roles.key нь 64 тэмдэгт хүртэл зөвшөөрдөг (admin-ээс нэмсэн роль
     * урт нэрнээс слаг хэлбэрээр үүсдэг) харин role_permissions.role нь
     * зөвхөн 32 тэмдэгттэй байсан тул урт нэртэй роль дээр эрх
     * хадгалахад "Data too long for column 'role'" 500 алдаа гардаг
     * байв. Хоёр баганыг тэнцүү (64) болгов.
     */
    public function up(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->string('role', 64)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->string('role', 32)->change();
        });
    }
};
