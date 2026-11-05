<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Гэрээ (тайлан) ирүүлэх хугацаа.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_schedules')) {
            return;
        }

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('report_key', 120)->unique();
            $table->date('due_on')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
