<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Тайлангийн мөрийн баталгаажуулалт.
 *
 * Хэлтсийн дарга мөрөө баталгаажуулж байж хяналт-шинжилгээний
 * үнэлгээнд очно.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_row_approvals')) {
            return;
        }

        Schema::create('report_row_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('report_key', 120);
            $table->unsignedInteger('row_index');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['report_key', 'row_index'], 'report_row_approval_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_row_approvals');
    }
};
