<?php

namespace Database\Seeders;

use App\Models\System;
use Illuminate\Database\Seeder;

class SystemSeeder extends Seeder
{
    public function run(): void
    {
        $systems = [
            [
                'slug' => 'uureg-biyelelt',
                'name' => 'Үүрэг, чиглэлийн биелэлт',
                'url' => 'https://boldsaikhan.github.io/festival-tuluvluguu/',
                'login_url' => null,
                'description' => 'Аймгийн Засаг даргын үүрэг, чиглэл болон өвөлжилт, хаваржилтын бэлтгэлийн биелэлтийн хяналтын дашбоард.',
                'category' => 'Хяналт',
                'icon' => 'clipboard',
                'sort_order' => 5,
                // Нийтэд нээлттэй статик сайт — нэвтрэх мэдээлэл шаардахгүй, мөн
                // X-Frame-Options илгээдэггүй тул iframe дотор шууд суудаг.
                'requires_login' => false,
                'is_embeddable' => true,
                'is_internal' => true,
                // Энэ систем апп дотор нэгтгэгдсэн (`/uureg`, `TaskController`) тул
                // гадаад холбоосыг цэснээс нуув. Түүхэн мэдээллийн хувьд үлдээв.
                'is_active' => false,
            ],
            [
                'slug' => 'shilen-dans',
                'name' => 'Шилэн данс',
                'url' => 'https://shilen.gov.mn/home',
                'login_url' => 'https://shilen.gov.mn/home',
                'description' => 'Төсвийн ил тод байдал, гүйлгээний мэдээллийн нэгдсэн систем.',
                'category' => 'Санхүү',
                'icon' => 'wallet',
                'sort_order' => 10,
            ],
            [
                'slug' => 'erp-e-mongolia',
                'name' => 'Төрийн ERP',
                // /home бол зөвхөн танилцуулга сайт; жинхэнэ систем нь үндсэн домэйн дээр.
                'url' => 'https://erp.e-mongolia.mn/',
                'login_url' => 'https://erp.e-mongolia.mn/login',
                'description' => 'Төрийн байгууллагын нөөцийн удирдлагын систем — санхүү, хүний нөөц, худалдан авалт.',
                'category' => 'ERP',
                'icon' => 'building',
                'sort_order' => 20,
            ],
            [
                'slug' => 'gov-mn',
                'name' => 'Засгийн газрын портал',
                'url' => 'https://www.gov.mn/home',
                'login_url' => 'https://www.gov.mn/home',
                'description' => 'Төрийн байгууллагуудын нэгдсэн мэдээллийн портал.',
                'category' => 'Мэдээлэл',
                'icon' => 'globe',
                'sort_order' => 30,
            ],
            [
                'slug' => 'mail-gov-mn',
                'name' => 'Төрийн шуудан',
                'url' => 'https://mail.gov.mn/',
                'login_url' => 'https://mail.gov.mn/',
                'description' => 'Албан ёсны цахим шуудангийн систем.',
                'category' => 'Шуудан',
                'icon' => 'mail',
                'sort_order' => 40,
            ],
            [
                'slug' => 'unelgee',
                'name' => 'Үнэлгээний систем',
                'url' => 'http://dornogovi.unelgee.gov.mn/home/',
                'login_url' => 'https://loginnew.unelgee.gov.mn/?continue=http%3A%2F%2Fdornogovi.unelgee.gov.mn%2Fhome%2F',
                'description' => 'Төрийн албан хаагчийн гүйцэтгэлийн үнэлгээний систем.',
                'category' => 'Үнэлгээ',
                'icon' => 'chart',
                'sort_order' => 50,
            ],
            [
                'slug' => 'sudalgaa-dornogovi',
                'name' => 'Судалгааны систем',
                'url' => 'https://sudalgaa.dornogovi.gov.mn/',
                'login_url' => 'https://sudalgaa.dornogovi.gov.mn/',
                'description' => 'Дорноговь аймгийн дотоод судалгаа, санал асуулгын систем.',
                'category' => 'Судалгаа',
                'icon' => 'clipboard',
                'sort_order' => 60,
            ],
        ];

        foreach ($systems as $system) {
            System::updateOrCreate(['slug' => $system['slug']], $system);
        }
    }
}
