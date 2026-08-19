<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            // null = шалгаагүй, true = iframe-д суудаг, false = сервер нь хориглосон
            $table->boolean('is_embeddable')->nullable()->after('icon');
            $table->string('embed_blocked_by')->nullable()->after('is_embeddable');
            $table->timestamp('embed_checked_at')->nullable()->after('embed_blocked_by');
        });
    }

    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropColumn(['is_embeddable', 'embed_blocked_by', 'embed_checked_at']);
        });
    }
};
