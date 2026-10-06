<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * «Систем бүртгэх» хэсэгт URL талбаруудыг validate хийхдээ 2000
     * тэмдэгт хүртэл зөвшөөрдөг байсан ч баганууд нь varchar(255) байсан
     * тул 255-аас урт URL (жишээ нь урт нэвтрэх линк) оруулахад
     * "Data too long" алдаа гарч 500 болж байв.
     */
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->string('url', 2000)->change();
            $table->string('login_url', 2000)->nullable()->change();
            $table->string('login_form_action', 2000)->nullable()->change();
            $table->string('dan_login_url', 2000)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->string('url', 255)->change();
            $table->string('login_url', 255)->nullable()->change();
            $table->string('login_form_action', 255)->nullable()->change();
            $table->string('dan_login_url', 255)->nullable()->change();
        });
    }
};
