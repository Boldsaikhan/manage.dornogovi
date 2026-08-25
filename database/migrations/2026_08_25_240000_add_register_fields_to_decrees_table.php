<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decrees', function (Blueprint $table) {
            // Захирамж/тушаалын бүртгэлийн хүснэгт (цаасан загвар)
            $table->unsignedSmallInteger('page_count')->nullable()->after('title');
            $table->string('attachment_name')->nullable()->after('page_count');
            $table->unsignedSmallInteger('attachment_pages')->nullable()->after('attachment_name');
        });
    }

    public function down(): void
    {
        Schema::table('decrees', function (Blueprint $table) {
            $table->dropColumn(['page_count', 'attachment_name', 'attachment_pages']);
        });
    }
};
