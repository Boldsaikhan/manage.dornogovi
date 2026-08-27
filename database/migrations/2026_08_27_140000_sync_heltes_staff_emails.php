<?php

use App\Services\HeltesAccountProvisioner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(HeltesAccountProvisioner::class)->syncStaffEmails();
    }

    public function down(): void
    {
        // И-мэйлийг хуучин phone@staff хаяг руу буцаахгүй.
    }
};
