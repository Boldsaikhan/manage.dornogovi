<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            // Тухайн систем ДАН (Үндэсний танилт нэвтрэлт)-ээр нэвтэрдэг эсэх.
            $table->boolean('supports_dan')->default(false)->after('login_extra_fields');
            $table->string('dan_login_url')->nullable()->after('supports_dan');
        });

        Schema::table('user_credentials', function (Blueprint $table) {
            // password = нэр/нууц үг, dan = регистр + ДАН нууц үг
            $table->string('auth_type')->default('password')->after('system_id');
            // Өргөтгөл уг төхөөрөмж дээр мэдээллийг санаж, дараа нь шууд нэвтрүүлнэ.
            $table->boolean('remember_device')->default(false)->after('note_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn(['supports_dan', 'dan_login_url']);
        });

        Schema::table('user_credentials', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'remember_device']);
        });
    }
};
