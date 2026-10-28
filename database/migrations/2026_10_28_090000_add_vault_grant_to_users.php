<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Админ албан хаагч бүрийн нэвтрэх мэдээллийн санг тусад нь нээх боломж.
 *
 * Зөвхөн нээлттэй байх хугацааг хадгална — нууц үг энд хадгалагдахгүй.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('vault_open_until')->nullable()->after('remember_token');
            $table->foreignId('vault_opened_by')->nullable()->after('vault_open_until')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vault_opened_by');
            $table->dropColumn('vault_open_until');
        });
    }
};
