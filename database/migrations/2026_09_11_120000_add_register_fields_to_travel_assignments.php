<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Цаасан бүртгэлээс оруулах боломжтой болгоно.
 *
 * Бүртгэлд системд эрхгүй хүн, гэрээт ажилтан, жолооч нар ч ордог тул
 * нэр, албан тушаалыг мөр дээрээ хадгална. Хугацаа нь заагаагүй мөр ч
 * байдаг тул дуусах огноог заавал биш болгов.
 *
 * Багана өөрчлөх алхмууд MySQL дээр (гадаад түлхүүр, давхар ажиллалт)
 * бүтэлгүйтвэл дараагийн бүх migration зогсох тул алхам бүрийг тусад нь
 * хамгаална. Эцсийн засварыг 2026_09_11_190000 migration гүйцээнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['person_name', 'position'] as $column) {
            if (Schema::hasColumn('travel_assignments', $column)) {
                continue;
            }

            Schema::table('travel_assignments', function (Blueprint $table) use ($column) {
                $table->string($column)->nullable();
            });
        }

        $this->attempt(function () {
            Schema::table('travel_assignments', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
            });
        });

        $this->attempt(function () {
            Schema::table('travel_assignments', function (Blueprint $table) {
                $table->date('end_date')->nullable()->change();
                $table->string('destination')->nullable()->change();
            });
        });
    }

    public function down(): void
    {
        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->dropColumn(['person_name', 'position']);
        });
    }

    private function attempt(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::info('travel_assignments багана өөрчлөх алхам алгаслаа: '.$e->getMessage());
        }
    }
};
