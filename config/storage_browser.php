<?php

/**
 * Системийн тохиргоо → Файлууд: зөвшөөрөгдсөн үндсэн хавтаснууд.
 */
return [
    'roots' => [
        'private' => [
            'label' => 'Системийн хадгалалт',
            'disk' => 'local',
            'prefix' => '',
        ],
        'public' => [
            'label' => 'Нийтийн storage',
            'disk' => 'public',
            'prefix' => '',
        ],
        'web_images' => [
            'label' => 'Веб зураг',
            'path' => public_path('images'),
        ],
        'web_icons' => [
            'label' => 'Дүрс тэмдэг',
            'path' => public_path('icons'),
        ],
    ],

    /** Эдгээр нэртэй хавтас/файлыг жагсаалтаас нууна. */
    'blocked_names' => [
        '.env',
        '.git',
        '.svn',
        'cgi-bin',
        'node_modules',
        'vendor',
        'framework',
    ],

    'image_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'],
];
