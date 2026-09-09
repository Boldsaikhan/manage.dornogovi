<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * verify.mn нь MO SMS — хэрэглэгч өөрөө 144773 руу код илгээнэ.
 * Тиймээс заавар, sms: холбоос, сувгийг нь хадгална.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_verifications', function (Blueprint $table) {
            $table->string('channel', 20)->default('sms')->after('purpose');
            $table->string('sms_uri')->nullable()->after('session_id');
            $table->text('instruction')->nullable()->after('sms_uri');
        });
    }

    public function down(): void
    {
        Schema::table('phone_verifications', function (Blueprint $table) {
            $table->dropColumn(['channel', 'sms_uri', 'instruction']);
        });
    }
};
