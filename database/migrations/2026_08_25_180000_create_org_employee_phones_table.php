<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_employee_phones', function (Blueprint $table) {
            $table->id();
            $table->string('organization'); // Байгууллага
            $table->string('unit')->nullable(); // Нэгж
            $table->string('position')->nullable(); // Албан тушаал
            $table->string('last_name'); // Овог
            $table->string('first_name'); // Нэр
            $table->string('room', 64)->nullable(); // Өрөө
            $table->string('work_phone', 64)->nullable(); // Ажлын утас
            $table->string('mobile_phone', 64)->nullable(); // Гар утас
            $table->string('email')->nullable(); // И-Мэйл хаяг
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_employee_phones');
    }
};
