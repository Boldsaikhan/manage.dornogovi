<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Томилолтын удирдамж» маягтад шаардлагатай талбарууд.
 *
 * approver — маягтын «БАТЛАВ» хэсэгт хэн гарын үсэг зурахыг заана.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->string('approver', 30)->default('governor')->after('department_id')->index();
            $table->text('composition')->nullable()->after('purpose');
            $table->text('scope_of_work')->nullable()->after('composition');
            $table->text('report')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('travel_assignments', function (Blueprint $table) {
            $table->dropColumn(['approver', 'composition', 'scope_of_work', 'report']);
        });
    }
};
