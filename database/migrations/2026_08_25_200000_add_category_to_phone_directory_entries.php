<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_directory_entries', function (Blueprint $table) {
            // agentlag | sum | baiguullaga — чөлөөний хамрах хүрээтэй уялдана.
            $table->string('category', 16)->default('baiguullaga')->after('org_name');
            $table->index('category');
        });

        // Одоо байгаа бүртгэлийг нэрээр нь таамаглан ангилна.
        DB::table('phone_directory_entries')
            ->where('org_name', 'like', '%сум%')
            ->update(['category' => 'sum']);

        DB::table('phone_directory_entries')
            ->where('org_name', 'like', '%агентлаг%')
            ->update(['category' => 'agentlag']);
    }

    public function down(): void
    {
        Schema::table('phone_directory_entries', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
