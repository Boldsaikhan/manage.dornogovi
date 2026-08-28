<?php

use App\Services\HeltesAccountProvisioner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(HeltesAccountProvisioner::class)->setAllLoginPasswords('ZDTG@22026');
    }

    public function down(): void
    {
        // Хуучин нууц үг рүү буцаах боломжгүй.
    }
};
