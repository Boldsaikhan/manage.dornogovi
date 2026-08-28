<?php

use App\Services\HeltesAccountProvisioner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(HeltesAccountProvisioner::class)->setAdminLoginPasswords('Boldoo@1134');
    }

    public function down(): void
    {
        // Хуучин нууц үг рүү буцаах боломжгүй.
    }
};
