<?php

use App\Services\TrainingParticipationSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(TrainingParticipationSeeder::class)->syncMonitors();
    }

    public function down(): void
    {
        //
    }
};
