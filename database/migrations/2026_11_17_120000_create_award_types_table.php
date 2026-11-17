<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('award_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $names = [
            'Монгол Улсын Баатар цол',
            'Монгол Улсын Хөдөлмөрийн баатар цол',
            'Ардын багш цол',
            'Ардын жүжигчин цол',
            'Ардын зураач цол',
            'Ардын уран зохиолч цол',
            'Ардын эмч цол',
            'Аж үйлдвэрийн гавьяат ажилтан',
            'Байгаль орчны гавьяат ажилтан',
            'Гавьяат агрономич',
            'Гавьяат барилгачин',
            'Гавьяат жүжигчин',
            'Гавьяат мал зүйч',
            'Гавьяат малчин',
            'Гавьяат тамирчин',
            'Гавьяат тээвэрчин',
            'Гавьяат усжуулагч',
            'Гавьяат уурхайчин',
            'Гавьяат холбоочин',
            'Гавьяат хуульч',
            'Малын гавьяат эмч',
            'Ойн гавьяат ажилтан',
            'Соёлын гавьяат зүтгэлтэн',
            'Үйлчилгээний гавьяат ажилтан',
            'Урлагийн гавьяат зүтгэлтэн',
            'Ус цаг уурын гавьяат ажилтан',
            'Хөдөө аж ахуйн гавьяат ажилтан',
            'Худалдааны гавьяат ажилтан',
            'Хүний гавьяат эмч',
            'Шинжлэх ухааны гавьяат зүтгэлтэн',
            'Эрүүл мэндийг хамгаалах гавьяат ажилтан',
            'Сүхбаатарын одон',
            'Хөдөлмөрийн гавьяаны улаан тугийн одон',
            'Цэргийн гавьяаны улаан тугийн одон',
            'Хөдөлмөрийн хүндэт медаль',
            'Цэргийн хүндэт медаль',
            'Шударга журам медаль',
            'Найрамдал медаль',
        ];

        foreach ($names as $index => $name) {
            DB::table('award_types')->insert([
                'name' => $name,
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('award_types');
    }
};
