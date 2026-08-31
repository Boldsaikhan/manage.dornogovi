<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulation_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('regulation_categories')->insert([
            ['key' => 'internal', 'label' => 'Дотоод журам', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'labor_law', 'label' => 'Хөдөлмөрийн тухай хууль', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'cyber_security', 'label' => 'Кибер аюулгүй байдлын дотоод журам', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('regulation_categories');
    }
};
