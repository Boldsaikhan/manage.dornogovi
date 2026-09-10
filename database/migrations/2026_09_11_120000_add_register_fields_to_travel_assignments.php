<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Цаасан бүртгэлээс оруулах боломжтой болгоно.
 *
 * Бүртгэлд системд эрхгүй хүн, гэрээт ажилтан, жолооч нар ч ордог тул
 * нэр, албан тушаалыг мөр дээрээ хадгална. Хугацаа нь заагаагүй мөр ч
 * байдаг тул дуусах огноог заавал биш болгов.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->string('person_name')->nullable()->after('user_id');
            $table->string('position')->nullable()->after('person_name');
        });

        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->date('end_date')->nullable()->change();
            $table->string('destination')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->dropColumn(['person_name', 'position']);
        });
    }
};
