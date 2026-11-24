<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Нүдний утгын задаргаа — нэг тоог бүрдүүлсэн мөрүүд.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sector_indicator_value_items')) {
            return;
        }

        Schema::create('sector_indicator_value_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_indicator_value_id')
                ->constrained('sector_indicator_values')
                ->cascadeOnDelete();
            $table->string('label', 500);
            $table->string('amount', 60)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sector_indicator_value_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_indicator_value_items');
    }
};
