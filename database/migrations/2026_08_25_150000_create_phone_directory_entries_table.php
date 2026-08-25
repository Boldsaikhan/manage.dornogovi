<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_directory_entries', function (Blueprint $table) {
            $table->id();
            $table->string('org_name'); // Байгууллага/хэлтэс — хүснэгтийн бүлгийн гарчиг
            $table->unsignedSmallInteger('org_order')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('person_name');
            $table->string('position')->nullable();
            $table->string('office_phone', 64)->nullable();
            $table->string('mobile_phone', 64)->nullable();
            $table->timestamps();

            $table->index(['org_order', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_directory_entries');
    }
};
