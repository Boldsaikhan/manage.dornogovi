<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->string('slip_number')->nullable()->after('person_name');
            $table->string('signer', 16)->default('acting')->after('slip_number'); // acting|head
        });

        // Хуучин төрлийг чөлөөний хуудасны ангилал руу шилжүүлнэ.
        DB::table('leaves')->where('type', 'chuluu')->update(['type' => 'tsalintai']);
        DB::table('leaves')->where('type', 'amralt')->update(['type' => 'eeljiin']);
        DB::table('leaves')->where('type', 'busad')->update(['type' => 'tsalingui']);
    }

    public function down(): void
    {
        DB::table('leaves')->where('type', 'tsalintai')->update(['type' => 'chuluu']);
        DB::table('leaves')->where('type', 'eeljiin')->update(['type' => 'amralt']);
        DB::table('leaves')->where('type', 'tsalingui')->update(['type' => 'busad']);

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn(['slip_number', 'signer']);
        });
    }
};
