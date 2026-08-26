<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();

            // pending → approved → consumed. Татгалзсан бол rejected.
            $table->string('status', 20)->default('pending');

            // Зөвшөөрсөн хэрэглэгч (утсан дээрх эрх).
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            // QR-ыг үүсгэсэн (нэвтрэхийг хүсэж буй) төхөөрөмжийн мөр.
            $table->string('requester_ip', 45)->nullable();
            $table->string('requester_agent', 500)->nullable();
            $table->string('session_id', 100)->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            // nullable — MySQL-ийн strict горимд NOT NULL timestamp нь default шаарддаг.
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_qr_tokens');
    }
};
