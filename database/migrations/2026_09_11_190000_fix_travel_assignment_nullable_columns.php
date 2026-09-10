<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Томилолтын бүртгэлийн багануудыг найдвартай заавал биш болгоно.
 *
 * Өмнөх migration нь user_id багана дээр гадаад түлхүүр байсан тул MySQL
 * дээр бүтэлгүйтэж, цаасан бүртгэлээс оруулахад «500» алдаа өгч байв.
 * Гадаад түлхүүрийг түр хасаад, өөрчлөөд, дахин үүсгэнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('travel_assignments', 'person_name')) {
            Schema::table('travel_assignments', function (Blueprint $table) {
                $table->string('person_name')->nullable()->after('user_id');
            });
        }

        if (! Schema::hasColumn('travel_assignments', 'position')) {
            Schema::table('travel_assignments', function (Blueprint $table) {
                $table->string('position')->nullable()->after('person_name');
            });
        }

        // Гадаад түлхүүртэй баганыг өөрчлөхийн өмнө түлхүүрийг нь хасна.
        $this->attempt(function () {
            Schema::table('travel_assignments', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        });

        $this->attempt(function () {
            Schema::table('travel_assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        });

        $this->attempt(function () {
            Schema::table('travel_assignments', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        });

        foreach (['start_date', 'end_date'] as $column) {
            $this->attempt(function () use ($column) {
                Schema::table('travel_assignments', function (Blueprint $table) use ($column) {
                    $table->date($column)->nullable()->change();
                });
            });
        }

        $this->attempt(function () {
            Schema::table('travel_assignments', function (Blueprint $table) {
                $table->string('destination')->nullable()->change();
            });
        });
    }

    public function down(): void
    {
        // Буцаахгүй — заавал биш болгосныг эргүүлбэл байгаа мөрүүд эвдэрнэ.
    }

    /**
     * Аль хэдийн хийгдсэн алхам дээр deploy зогсохоос сэргийлнэ.
     */
    private function attempt(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::info('travel_assignments багана өөрчлөх алхам алгаслаа: '.$e->getMessage());
        }
    }
};
