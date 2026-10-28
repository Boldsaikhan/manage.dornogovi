<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Салбарын үзүүлэлт» — нэгж бүрийн суурь тоон мэдээлэл.
 *
 * Жилийн багануудыг гараар удирдана (нэг жил нэг эсвэл хоёр нүдтэй),
 * утгууд нь мөр × багана огтлолцол бүрээр хадгалагдана.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Жилийн багана — «2024 он», хагас жил/жилийн эцэс гэж хуваагдана.
        if (! Schema::hasTable('indicator_periods')) {
            Schema::create('indicator_periods', function (Blueprint $table) {
                $table->id();
                $table->string('label', 40);
                $table->unsignedSmallInteger('year')->nullable();
                // true бол «хагас жил» + «жилийн эцэс» гэж хоёр нүдтэй.
                $table->boolean('split')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index('sort_order');
            });
        }

        // Үзүүлэлтийн мөр.
        if (! Schema::hasTable('sector_indicators')) {
            Schema::create('sector_indicators', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                // Нэгж устсан ч нэр нь үлдэнэ.
                $table->string('unit_name')->nullable();
                $table->text('title')->nullable();
                $table->string('measure_unit', 60)->nullable();
                // «Хүрэх түвшин».
                $table->string('target', 60)->nullable();
                $table->text('note')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['department_id', 'sort_order']);
            });
        }

        // Мөр × багана огтлолцлын утга.
        if (! Schema::hasTable('sector_indicator_values')) {
            Schema::create('sector_indicator_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sector_indicator_id')->constrained()->cascadeOnDelete();
                $table->foreignId('indicator_period_id')->constrained()->cascadeOnDelete();
                // 'half' = хагас жил, 'year' = жилийн эцэс.
                $table->string('slot', 8)->default('year');
                $table->string('value', 60)->nullable();
                $table->timestamps();

                $table->unique(
                    ['sector_indicator_id', 'indicator_period_id', 'slot'],
                    'indicator_value_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_indicator_values');
        Schema::dropIfExists('sector_indicators');
        Schema::dropIfExists('indicator_periods');
    }
};
