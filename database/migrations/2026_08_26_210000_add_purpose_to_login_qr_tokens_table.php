<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_qr_tokens', function (Blueprint $table) {
            // login = компьютерт нэвтрэх | vault_unlock = нэвтрэх мэдээллийн сан нээх
            $table->string('purpose', 32)->default('login')->after('status');
            $table->foreignId('expected_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('login_qr_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('login_qr_tokens', 'expected_user_id')) {
                $table->dropConstrainedForeignId('expected_user_id');
            }
            if (Schema::hasColumn('login_qr_tokens', 'purpose')) {
                $table->dropColumn('purpose');
            }
        });
    }
};
