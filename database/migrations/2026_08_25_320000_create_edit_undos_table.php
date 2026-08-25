<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Хэрэглэгчийн сүүлийн үйлдлүүд — «Буцаах» боломжид (дахин ачаалсан ч хадгалагдана).
        Schema::create('edit_undos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('label')->nullable();       // Жишээ: «Захирамж, тушаал»
            $table->string('summary')->nullable();     // Жишээ: «Дугаар: 01 → 02»
            $table->json('payload');                   // өмнөх утгууд
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edit_undos');
    }
};
