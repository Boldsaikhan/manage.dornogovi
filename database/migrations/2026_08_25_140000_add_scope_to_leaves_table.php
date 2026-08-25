<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // agentlag|sum|baiguullaga — чөлөөний бүртгэлийн хамрах хүрээ.
            $table->string('scope', 24)->default('baiguullaga')->after('department_id');
            $table->string('org_name')->nullable()->after('scope');
            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropColumn(['scope', 'org_name']);
        });
    }
};
