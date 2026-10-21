<?php

use App\Models\AppSetting;
use App\Support\LeaveBalance;
use App\Support\LeaveLimits;
use Illuminate\Database\Migrations\Migration;

/**
 * Чөлөөний хоногийг заалт тус бүрээр нь тооцно.
 *
 * Өмнө нь бүх цалинтай чөлөөг нэг нийлбэр хязгаарт (жилд 5 хоног)
 * оруулдаг байсан тул 5.4.12 (жилд 15 хоног) зэрэг заалт хүчингүй
 * болж байв. Нийлбэр хязгаарыг унтрааж, заалт бүрийн лимитээр нь
 * шалгадаг болгоно.
 */
return new class extends Migration
{
    public function up(): void
    {
        AppSetting::query()->where('key', LeaveLimits::PAID_DAYS_KEY)->delete();

        LeaveLimits::forget();

        // Нийлбэр хязгаараар «Цалингүй» болгосон мөрүүдийг сэргээнэ.
        LeaveBalance::reapplyAll();
    }

    public function down(): void
    {
        // Буцаахгүй — нийлбэр хязгаарыг тохиргооноос дахин тавина.
    }
};
