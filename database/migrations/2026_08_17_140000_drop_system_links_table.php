<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Дотоод цэсний бүтцийг хассан. Платформ нь систем рүү шууд нэвтрэхэд төвлөрнө.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('system_links');
    }

    public function down(): void
    {
        Schema::create('system_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('system_links')->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['system_id', 'sort_order']);
        });
    }
};
