<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Өөрчлөлтийн лог.
 *
 * Мөр хэзээ, хэнээр, юунаас болж үүссэн/өөрчлөгдсөн/устсаныг тэмдэглэнэ —
 * «энэ мөр хаанаас гарч ирэв?» гэсэн асуултад хариулах боломжтой болно.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model_type', 64);
            $table->unsignedBigInteger('model_id')->nullable();
            // created | updated | deleted | imported | file_added | file_removed
            $table->string('action', 24);
            // Аль бүлэгт хамаарах (жнь decrees:zahiramj_a) — шүүхэд.
            $table->string('scope', 64)->nullable();
            $table->string('label')->nullable();
            $table->text('summary')->nullable();
            $table->json('changes')->nullable();
            $table->string('source', 32)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['model_type', 'model_id']);
            $table->index(['scope', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
