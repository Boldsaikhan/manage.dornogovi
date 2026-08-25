<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decrees', function (Blueprint $table) {
            // Хэвлэмэл хуудас авсан ажилтан
            $table->string('person_name')->nullable()->after('title');

            // Олгосон тоо (ширхэг)
            $table->unsignedSmallInteger('qty_zahiramj')->default(0)->after('person_name');
            $table->unsignedSmallInteger('qty_zahiramj_mn')->default(0)->after('qty_zahiramj');
            $table->unsignedSmallInteger('qty_tushaal')->default(0)->after('qty_zahiramj_mn');
            $table->unsignedSmallInteger('qty_tushaal_mn')->default(0)->after('qty_tushaal');
            $table->unsignedSmallInteger('qty_assignment')->default(0)->after('qty_tushaal_mn');
            $table->unsignedSmallInteger('qty_assignment_mn')->default(0)->after('qty_assignment');
            $table->unsignedSmallInteger('qty_council')->default(0)->after('qty_assignment_mn');
            $table->unsignedSmallInteger('qty_council_mn')->default(0)->after('qty_council');

            // Хэвлэмэл хуудасны дугаар (мужаа)
            $table->string('num_zahiramj')->nullable()->after('qty_council_mn');
            $table->string('num_tushaal')->nullable()->after('num_zahiramj');

            // Үрэгдүүлсэн хуудасны дугаар
            $table->string('void_zahiramj')->nullable()->after('num_tushaal');
            $table->string('void_tushaal')->nullable()->after('void_zahiramj');
        });

        // Хуучин «decree» бүртгэлийг төрлөөр нь салгах
        DB::table('decrees')->where('category', 'decree')->whereIn('kind', ['zahiramj_a', 'zahiramj_b'])->update(['category' => 'zahiramj']);
        DB::table('decrees')->where('category', 'decree')->whereIn('kind', ['tushaal_a', 'tushaal_b'])->update(['category' => 'tushaal']);
        DB::table('decrees')->where('category', 'decree')->update(['category' => 'zahiramj']);
    }

    public function down(): void
    {
        Schema::table('decrees', function (Blueprint $table) {
            $table->dropColumn([
                'person_name',
                'qty_zahiramj', 'qty_zahiramj_mn', 'qty_tushaal', 'qty_tushaal_mn',
                'qty_assignment', 'qty_assignment_mn', 'qty_council', 'qty_council_mn',
                'num_zahiramj', 'num_tushaal', 'void_zahiramj', 'void_tushaal',
            ]);
        });
    }
};
