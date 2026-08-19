<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_links', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('system_id')
                ->constrained('system_links')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('system_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
