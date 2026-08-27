<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            // state_high | governor_honor | governor_leading | other
            $table->string('category', 40);
            // orgomjol | juukh | team | employee | null
            $table->string('subtype', 40)->nullable();
            $table->unsignedSmallInteger('year')->nullable();

            $table->string('surname')->nullable();
            $table->string('given_name')->nullable();
            $table->string('register_no', 30)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender', 20)->nullable();

            // Төрийн дээд
            $table->string('nominated_award')->nullable();
            $table->unsignedSmallInteger('years_in_country')->nullable();
            $table->unsignedSmallInteger('years_in_sector')->nullable();
            $table->date('award_date')->nullable();
            $table->string('resolution_number')->nullable();
            $table->text('position')->nullable();
            $table->text('address')->nullable();
            $table->string('last_award')->nullable();
            $table->string('supporting_org')->nullable();
            $table->text('presidential_letter')->nullable();

            // АЗД өргөмжлөл/жуух, тэргүүний, бусад
            $table->string('award_name')->nullable();
            $table->string('work_sector')->nullable();
            $table->text('job_title')->nullable();
            $table->unsignedSmallInteger('total_years')->nullable();
            $table->unsignedSmallInteger('position_years')->nullable();
            $table->string('order_ref')->nullable();
            $table->text('award_note')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'subtype']);
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
