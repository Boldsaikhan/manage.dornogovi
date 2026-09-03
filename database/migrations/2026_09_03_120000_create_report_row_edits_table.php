<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Тайлангийн мөрийн засвар.
 *
 * Эх өгөгдөл нь repo доторх JSON тул түүн рүү бичих боломжгүй (deploy бүрт
 * `git reset --hard` хийгддэг). Тиймээс хэрэглэгчийн засварыг энд хадгална.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_row_edits', function (Blueprint $table) {
            $table->id();
            $table->string('report_key', 120);
            $table->unsignedInteger('row_index');
            $table->string('column_key', 64);
            $table->text('value')->nullable();

            // «Хэлтэс» баганад — жинхэнэ хэлтсийн холбоос (харагдах хүрээг тогтооно).
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['report_key', 'row_index', 'column_key'], 'report_row_edits_cell_unique');
            $table->index(['report_key', 'column_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_row_edits');
    }
};
