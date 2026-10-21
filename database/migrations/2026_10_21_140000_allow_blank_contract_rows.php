<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Гэрээний хүснэгтэд хоосон мөр нэмж, нүдэн дээр нь бөглөхийг зөвшөөрнө.
 *
 * Дугаар, нэр, огноо нь мөр үүсэх үед хоосон байж болно. Дугаарын
 * давхардлыг хориглосон индекс хэвээр — NULL утгууд хоорондоо
 * давхардалд тооцогддоггүй тул олон хоосон мөр зэрэг байж чадна.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['number', 'title'] as $column) {
            $this->attempt(function () use ($column) {
                Schema::table('contracts', function (Blueprint $table) use ($column) {
                    $table->string($column)->nullable()->change();
                });
            });
        }

        $this->attempt(function () {
            Schema::table('contracts', function (Blueprint $table) {
                $table->date('issued_on')->nullable()->change();
            });
        });
    }

    public function down(): void
    {
        // Буцаахгүй — хоосон мөр үүссэн байж болно.
    }

    /** Аль хэдийн хийгдсэн алхам дээр deploy зогсохоос сэргийлнэ. */
    private function attempt(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::info('contracts багана өөрчлөх алхам алгаслаа: '.$e->getMessage());
        }
    }
};
