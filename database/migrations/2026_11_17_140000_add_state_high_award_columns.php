<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->string('profession')->nullable()->after('work_sector');
            $table->date('last_award_date')->nullable()->after('last_award');
            $table->date('forwarded_on')->nullable()->after('supporting_org');
            $table->string('forwarded_letter_no')->nullable()->after('forwarded_on');
            $table->string('received_award')->nullable()->after('forwarded_letter_no');
        });
    }

    public function down(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->dropColumn([
                'profession',
                'last_award_date',
                'forwarded_on',
                'forwarded_letter_no',
                'received_award',
            ]);
        });
    }
};
