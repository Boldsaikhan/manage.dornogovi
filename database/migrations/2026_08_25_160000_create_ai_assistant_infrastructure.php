<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('user_id')->constrained('ai_conversations')->nullOnDelete();
        });

        Schema::create('ai_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('question', 500)->nullable();
            $table->string('intent', 64)->nullable();
            $table->json('tools')->nullable();
            $table->json('sources')->nullable();
            $table->string('provider', 32)->nullable();
            $table->boolean('success')->default(true);
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('ai_daily_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedInteger('questions')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'usage_date']);
        });

        $now = now();
        DB::table('app_settings')->insert([
            ['key' => 'ai.provider', 'value' => 'local', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'ai.openai_model', 'value' => 'gpt-4o-mini', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'ai.daily_question_limit', 'value' => '30', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'ai.enabled', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_daily_usages');
        Schema::dropIfExists('ai_audit_logs');

        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
        });

        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('app_settings');
    }
};
