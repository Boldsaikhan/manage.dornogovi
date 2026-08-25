<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_formats', function (Blueprint $table) {
            $table->id();
            $table->string('key', 16)->unique(); // a4 | a5
            $table->string('label');
            $table->unsignedSmallInteger('width_mm');
            $table->unsignedSmallInteger('height_mm');
            $table->unsignedSmallInteger('margin_top_mm');
            $table->unsignedSmallInteger('margin_right_mm');
            $table->unsignedSmallInteger('margin_bottom_mm');
            $table->unsignedSmallInteger('margin_left_mm');
            $table->string('font_name', 64)->default('Arial');
            $table->decimal('font_size_pt', 4, 1)->default(12.0);
            $table->decimal('line_spacing', 3, 2)->default(1.00);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        $now = now();

        // Албан хэрэг хөтлөлтийн нийтлэг журмын анхны утга — тохиргооноос засварлаж болно.
        DB::table('document_formats')->insert([
            [
                'key' => 'a4', 'label' => 'A4 (албан бичиг)',
                'width_mm' => 210, 'height_mm' => 297,
                'margin_top_mm' => 20, 'margin_right_mm' => 15,
                'margin_bottom_mm' => 20, 'margin_left_mm' => 30,
                'font_name' => 'Arial', 'font_size_pt' => 12.0, 'line_spacing' => 1.00,
                'is_default' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'a5', 'label' => 'A5 (тодорхойлолт, маягт)',
                'width_mm' => 148, 'height_mm' => 210,
                'margin_top_mm' => 15, 'margin_right_mm' => 10,
                'margin_bottom_mm' => 15, 'margin_left_mm' => 25,
                'font_name' => 'Arial', 'font_size_pt' => 11.0, 'line_spacing' => 1.00,
                'is_default' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_formats');
    }
};
