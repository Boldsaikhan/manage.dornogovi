<?php

use App\Support\RootAdmin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Хоосон үүссэн үндсэн супер админыг засна.
 *
 * Өмнөх migration нь deploy дээр `config:cache`-ээс өмнө ажилласан тул хуучин
 * кэшэнд `root_admin.*` байгаагүй — нэр, и-мэйл, утасгүй супер админ үүсчихсэн.
 * Тийм бүртгэлийг зөв утгуудаар нөхнө (илүү гарсныг нь устгана).
 */
return new class extends Migration
{
    public function up(): void
    {
        $broken = DB::table('users')
            ->where('is_admin', true)
            ->whereNull('phone')
            ->where(function ($query) {
                $query->whereNull('name')->orWhere('name', '');
            })
            ->where(function ($query) {
                $query->whereNull('email')->orWhere('email', '');
            })
            ->orderBy('id')
            ->pluck('id');

        if ($broken->isNotEmpty()) {
            // Эхнийхийг үндсэн админ болгож нөхнө, үлдсэн хоосон бүрхүүлийг устгана.
            DB::table('users')->where('id', $broken->first())->update([
                'name' => RootAdmin::DEFAULTS['name'],
                'email' => RootAdmin::DEFAULTS['email'],
                'phone' => RootAdmin::DEFAULTS['phone'],
                'updated_at' => now(),
            ]);

            if ($broken->count() > 1) {
                DB::table('users')->whereIn('id', $broken->slice(1)->all())->delete();
            }
        }

        // Нууц үгийг нь дахин тавьж, эрхийг баталгаажуулна.
        RootAdmin::ensure(RootAdmin::DEFAULTS['password']);
    }

    public function down(): void
    {
        // Буцаахгүй — үндсэн админ алга болвол системд орох арга үлдэхгүй.
    }
};
