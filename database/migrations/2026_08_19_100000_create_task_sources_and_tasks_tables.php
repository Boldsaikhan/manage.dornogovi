<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Үүрэг, чиглэлийн биелэлт — өмнө нь тусдаа статик сайт (localStorage) байсныг
     * апп дотор нэгтгэв. Одоо өгөгдөл нэг санд бөгөөд бүх хэрэглэгч ижил
     * мэдээллийг харна.
     */
    public function up(): void
    {
        Schema::create('task_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // "2026.07.09 — Аймгийн Засаг даргын үүрэг, чиглэл"
            $table->string('period')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_source_id')->constrained()->cascadeOnDelete();
            $table->text('text');                              // үүрэг, чиглэл / ажил
            $table->string('period')->nullable();              // "07.09" | "07.28-09.28"
            $table->string('responsible')->nullable();         // үндсэн хэрэгжүүлэгч
            $table->string('collaborator')->nullable();        // хамтран / хяналт
            $table->string('sector')->nullable();              // салбар (зөвхөн 2-р эх сурвалж)
            $table->string('department')->nullable();          // хэлтэс — хэрэглэгч оноодог
            $table->string('indicator')->nullable();           // шалгуур үзүүлэлт, хэмжих нэгж
            $table->string('baseline')->nullable();            // суурь түвшин
            $table->string('target')->nullable();              // хүрэх түвшин
            $table->unsignedTinyInteger('progress')->default(0); // хэрэгжилт, 0–100
            $table->text('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['task_source_id', 'sort_order']);
            $table->index('department');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_sources');
    }
};
