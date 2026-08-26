<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_qr_tokens', function (Blueprint $table) {
            $table->string('client_secret_hash', 64)->nullable()->after('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('login_qr_tokens', function (Blueprint $table) {
            $table->dropColumn('client_secret_hash');
        });
    }
};
