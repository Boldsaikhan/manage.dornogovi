<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            // manual  = хаяг нээгээд нэр/нууц үгийг хуулж өгнө
            // form_post = нуугдмал маягтаар шууд илгээж нэвтэрнэ
            $table->string('login_method')->default('manual')->after('login_url');
            $table->string('login_form_action')->nullable()->after('login_method');
            $table->string('login_username_field')->nullable()->after('login_form_action');
            $table->string('login_password_field')->nullable()->after('login_username_field');
            $table->json('login_extra_fields')->nullable()->after('login_password_field');
        });
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn([
                'login_method',
                'login_form_action',
                'login_username_field',
                'login_password_field',
                'login_extra_fields',
            ]);
        });
    }
};
