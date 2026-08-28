<?php

use App\Services\PhoneDirectoryStaffSyncer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $path = database_path('data/phone-list-staff.json');

        if (! is_file($path)) {
            return;
        }

        $people = json_decode((string) file_get_contents($path), true);

        if (! is_array($people) || $people === []) {
            return;
        }

        app(PhoneDirectoryStaffSyncer::class)->sync($people);
    }

    public function down(): void
    {
        // Excel-ийн жагсаалт руу буцаах боломжгүй.
    }
};
