<?php

use App\Support\RootAdmin;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Үндсэн супер админыг үүсгэнэ (байвал эрх, нэвтрэх нэрийг нь баталгаажуулна).
     */
    public function up(): void
    {
        RootAdmin::ensure((string) config('root_admin.password'));
    }

    public function down(): void
    {
        // Устгахгүй — буцаахад үндсэн админ алга болвол системд орох арга үлдэхгүй.
    }
};
