<?php

use App\Models\AnnualLeave;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Мэдэгдлийн бичвэр, «Зөвшөөрсөн» хэсэг, өөрийн гарын үсгийн мөрийг
     * 3 тусдаа талбарт засдаг байснаас зөвхөн 1 талбарт (notice_text)
     * хамтад нь засдаг болгоно. Гараар засварласан өгөгдлийг алдахгүйн
     * тулд эхлээд нэгтгэж notice_text рүү шилжүүлнэ.
     */
    public function up(): void
    {
        AnnualLeave::query()
            ->where(function ($q) {
                $q->whereNotNull('approver_block_override')
                    ->orWhereNotNull('own_block_override');
            })
            ->whereNull('notice_text')
            ->get()
            ->each(function (AnnualLeave $row) {
                $row->update([
                    'notice_text' => \App\Support\AnnualLeaveNotice::fullText($row),
                ]);
            });

        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->dropColumn(['approver_block_override', 'own_block_override']);
        });
    }

    public function down(): void
    {
        Schema::table('annual_leaves', function (Blueprint $table) {
            $table->text('approver_block_override')->nullable()->after('signer');
            $table->text('own_block_override')->nullable()->after('approver_block_override');
        });
    }
};
