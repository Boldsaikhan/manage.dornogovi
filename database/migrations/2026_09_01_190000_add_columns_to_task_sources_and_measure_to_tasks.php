<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_sources', function (Blueprint $table) {
            $table->json('columns')->nullable()->after('layout');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->text('measure')->nullable()->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('measure');
        });

        Schema::table('task_sources', function (Blueprint $table) {
            $table->dropColumn('columns');
        });
    }
};
