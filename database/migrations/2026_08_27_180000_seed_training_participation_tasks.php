<?php

use App\Services\TrainingParticipationSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(TrainingParticipationSeeder::class)->run();
    }

    public function down(): void
    {
        // Үүсгэсэн үүрэг чиглэлийг автоматаар устгахгүй.
    }
};
