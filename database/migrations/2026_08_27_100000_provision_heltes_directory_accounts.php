<?php

use App\Services\HeltesAccountProvisioner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(HeltesAccountProvisioner::class)->run();
    }

    public function down(): void
    {
        // Нэвтрэх эрхийг автоматаар устгахгүй.
    }
};
