<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Эрх зүйн акт, тогтоол» бүртгэл.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legal_acts')) {
            return;
        }

        Schema::create('legal_acts', function (Blueprint $table) {
            $table->id();
            // Актын төрөл — хууль, тогтоол, тушаал гэх мэт.
            $table->string('scope', 32)->default('huuli');
            $table->string('title')->nullable();
            $table->string('number', 60)->nullable();
            $table->date('approved_on')->nullable();
            $table->date('effective_on')->nullable();
            // Аль салбар, хэлтэст хамаарах.
            $table->string('unit_name')->nullable();
            // legalinfo.mn болон бусад эх сурвалжийн холбоос.
            $table->string('source_url', 500)->nullable();
            $table->text('note')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'approved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acts');
    }
};
