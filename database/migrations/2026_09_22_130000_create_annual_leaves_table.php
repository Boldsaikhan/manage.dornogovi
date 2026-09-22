<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ээлжийн амралтын бүртгэл — чөлөөний хуудаснаас өөр, албан хаагчийн
 * ээлжийн амралтын хоног, огноо, эзгүй хугацаанд орлох хүнийг хөтөлнө.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            // agentlag|sum|baiguullaga — бусад бүртгэлтэй нийцсэн хамрах хүрээ.
            $table->string('scope', 24)->default('baiguullaga');
            $table->string('org_name')->nullable();
            $table->string('position')->nullable();
            $table->string('person_name')->nullable();
            $table->unsignedSmallInteger('work_years')->nullable();
            $table->unsignedSmallInteger('entitled_days')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('substitute_position')->nullable();
            $table->string('substitute_name')->nullable();
            $table->string('substitute_phone', 32)->nullable();
            $table->timestamps();

            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_leaves');
    }
};
