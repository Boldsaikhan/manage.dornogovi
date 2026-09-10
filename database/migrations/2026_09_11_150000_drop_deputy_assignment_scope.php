<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «Засаг даргын орлогч» хэсгийг хаав.
 *
 * Тэр хэсэгт бүртгэгдсэн мөр байвал алдагдахгүйн тулд «Засаг дарга» хэсэг
 * рүү шилжүүлнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('travel_assignments')
            ->where('approver', 'deputy')
            ->update(['approver' => 'governor']);
    }

    public function down(): void
    {
        // Буцаахгүй — аль мөр нь орлогчийнх байсныг ялгах боломжгүй.
    }
};
