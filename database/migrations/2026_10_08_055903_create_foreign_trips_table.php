<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('foreign_trips', function (Blueprint $table) {
            $table->id();
            // Хоосон мөр нэмээд дараа нь нүд бүрээр нь бөглөдөг тул
            // (бусад HR модулийн адил) бараг бүх талбар заавал биш.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 16)->default('baiguullaga'); // agentlag|sum|baiguullaga
            $table->string('org_name')->nullable();
            $table->string('position')->nullable();
            $table->string('person_name')->nullable();
            $table->string('destination_country')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('reason')->nullable();
            $table->text('companions')->nullable();
            $table->text('route')->nullable();
            $table->text('funding_source')->nullable();
            $table->text('foreign_contact')->nullable();
            $table->text('accommodation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foreign_trips');
    }
};
