<?php

/**
 * Тайлан мэдээлэл — ХШ_үүрэг даалгавар_ЭЦЭС.docx бүтэц.
 * Хүснэгтийн багана (columns) дараа нь тайлан бүрт нэмэгдэнэ.
 */

$deptChildren = static function (string $prefix, string $parentKey, array $departments): array {
    $rows = [];
    foreach ($departments as $code => $label) {
        $rows[] = [
            'key' => "{$parentKey}.{$code}",
            'number' => "{$prefix}.{$code}",
            'label' => $label,
            'department' => $code,
            'template' => 'policy_tracking',
            'columns' => [],
        ];
    }

    return $rows;
};

$policy = static fn (string $key, string $number, string $label, ?string $department = null, ?string $description = null): array => [
    'key' => $key,
    'number' => $number,
    'label' => $label,
    'department' => $department,
    'description' => $description,
    'template' => 'policy_tracking',
    'columns' => [],
];

return [
    'title' => 'Тайлан мэдээлэл',
    'subtitle' => 'Хяналт-шинжилгээ, үнэлгээний хэлтсээс хяналтад авсан баримт бичгийн жагсаалт',
    'source_document' => 'ХШ_үүрэг даалгавар_ЭЦЭС.docx',

    'sections' => [
        [
            'key' => 'state_policy',
            'number' => '1',
            'label' => 'Төрийн бодлого',
            'reports' => [
                $policy('state_policy.cabinet_decree', '1.1', 'Монгол Улсын Засгийн газрын тогтоол'),
                $policy('state_policy.cabinet_minutes', '1.2', 'Монгол Улсын Засгийн газрын хуралдааны тэмдэглэл'),
                $policy('state_policy.pm_directive', '1.3', 'Ерөнхий сайдын захирамж'),
                $policy('state_policy.pm_assignment', '1.4', 'Ерөнхий сайдын албан даалгавар'),
            ],
        ],
        [
            'key' => 'local_policy',
            'number' => '2',
            'label' => 'Орон нутгийн бодлого',
            'reports' => [
                $policy('local_policy.governor_program', '2.1', 'Аймгийн Засаг даргын үйл ажиллагааны хөтөлбөр'),
                $policy('local_policy.annual_plan', '2.2', 'Аймгийн хөгжлийн жилийн төлөвлөгөө'),
                $policy('local_policy.council_decision', '2.3', 'Аймгийн ИТХ-ын тогтоол'),
                [
                    'key' => 'local_policy.governor_directive',
                    'number' => '2.4',
                    'label' => 'Аймгийн Засаг даргын захирамж',
                    'template' => 'policy_tracking',
                    'columns' => [],
                    'children' => $deptChildren('2.4', 'local_policy.governor_directive', [
                        'tzux' => 'ТЗУХ',
                        'hezx' => 'ХЭЗХ',
                        'bonxo' => 'БОНХО',
                        'sxzx' => 'СХЗХ',
                        'stsx' => 'СТСХ',
                        'nbx' => 'НБХ',
                    ]),
                ],
                $policy('local_policy.law_implementation', '2.5', 'Хууль тогтоомж, тогтоол шийдвэрийн хэрэгжилт'),
                [
                    'key' => 'local_policy.leadership_tasks',
                    'number' => '2.6',
                    'label' => 'Удирдлагаас өгсөн үүрэг чиглэл',
                    'template' => 'policy_tracking',
                    'columns' => [],
                    'children' => $deptChildren('2.6', 'local_policy.leadership_tasks', [
                        'tzux' => 'ТЗУХ',
                        'hezx' => 'ХЭЗХ',
                        'bonxo' => 'БОНХО',
                        'sxzx' => 'СХЗХ',
                        'stsx' => 'СТСХ',
                        'nbx' => 'НБХ',
                    ]),
                ],
                [
                    'key' => 'local_policy.governor_assignments',
                    'number' => '2.7',
                    'label' => 'Аймгийн Засаг даргын албан даалгаврын хэрэгжилт',
                    'template' => 'policy_tracking',
                    'columns' => [],
                    'children' => [
                        $policy('local_policy.governor_assignments.script', '2.7.1', 'Үндэсний бичгийг хэрэглээ болгох ажлыг эрчимжүүлэх аймгийн засаг даргын 01 дүгээр албан даалгавар', 'НБХ'),
                        $policy('local_policy.governor_assignments.tree', '2.7.2', '«Тэрбум мод» үндэсний хөдөлгөөнийг эрчимжүүлэх аймгийн засаг даргын 02 дугаар албан даалгавар', 'БОНХО'),
                        $policy('local_policy.governor_assignments.health', '2.7.3', 'Эрүүл мэндийн салбарын үйл ажиллагааг сайжруулах, хамтын ажиллагааг өргөжүүлэх аймгийн засаг даргын 03 дугаар албан даалгавар', 'НБХ'),
                        $policy('local_policy.governor_assignments.budget', '2.7.4', 'Орон нутгийн 2025 оны төсвийн орлогын бүрдүүлэлтийн талаар авах зарим арга хэмжээний тухай аймгийн засаг даргын 04 дүгээр албан даалгавар', 'СТСХ'),
                        $policy('local_policy.governor_assignments.livestock', '2.7.5', 'Мал аж ахуйн салбарын 2025-2026 оны өвөлжилт, хаваржилтын бэлтгэл хангах зарим арга хэмжээний тухай аймгийн засаг даргын 06 дугаар албан даалгавар', 'БОНХО'),
                    ],
                ],
            ],
        ],
        [
            'key' => 'contracts',
            'number' => '3',
            'label' => 'Гэрээ',
            'reports' => [
                $policy('contracts.ministry_gbxn', '3.1', 'ГБХНХЯам — сайдуудтай хамтран ажиллах гэрээ', 'НБХ'),
                $policy('contracts.ministry_culture', '3.2', 'Соёлын сайд', null),
                $policy('contracts.ministry_health', '3.3', 'Эрүүл мэндийн сайд', null),
                $policy('contracts.ministry_education', '3.4', 'Боловсролын сайд', null),
                $policy('contracts.ministry_defense', '3.5', 'Батлан хамгаалахын сайд', 'ЦШ'),
                $policy('contracts.ministry_finance', '3.6', 'Сангийн сайд', 'СТСХ'),
                $policy('contracts.ministry_zgheg', '3.7', 'ЗГХЭГ', 'ТЗУХ'),
                $policy('contracts.deputy_gankhuyag', '3.8', 'Шадар сайд Х.Ганхуяг', null),
                $policy('contracts.ministry_digital', '3.9', 'Цахим хөгжлийн сайд', null),
                $policy('contracts.ministry_foreign', '3.10', 'Гадаад харилцааны сайд', null),
                $policy('contracts.deputy_dorjhand', '3.11', 'Шадар сайд Т.Доржханд', null),
                $policy('contracts.ministry_legal', '3.12', 'Хууль зүйн сайд', 'ХЭЗХ'),
                $policy('contracts.ministry_economy', '3.13', 'Эдийн засаг хөгжлийн сайд', 'БОНХО'),
                $policy('contracts.ministry_environment', '3.14', 'Байгаль орчны сайд', null),
                $policy('contracts.ministry_mram', '3.15', 'Аж үйлдвэр, эрдэс баялгийн сайд', null),
                $policy('contracts.ministry_urban', '3.16', 'Хот байгуулалтын сайд', null),
                $policy('contracts.ministry_food', '3.17', 'Хүнс, хөдөө аж ахуйн сайд', null),
                $policy('contracts.ministry_energy', '3.18', 'Эрчим хүчний сайд', null),
                $policy('contracts.ministry_road', '3.19', 'Зам, тээврийн сайд', null),
                $policy('contracts.agency_directors', '3.2.1', 'Агентлагийн дарга нартай хамтран ажиллах гэрээ'),
                $policy('contracts.soum_governors', '3.2.2', 'Сумдын Засаг дарга нартай хамтран ажиллах гэрээ'),
            ],
        ],
        [
            'key' => 'target_programs',
            'number' => '4',
            'label' => 'Зорилтот хөтөлбөр',
            'reports' => [
                $policy('target_programs.medical_education', '4.1', 'Анагаахын боловсрол олгох сургалтын байгууллагын хөгжлийг дэмжих'),
                $policy('target_programs.civil_service', '4.2', 'Төрийн захиргааны албан хаагчдын ажиллах нөхцөлийг сайжруулах, нийгмийн баталгааг хангах'),
                $policy('target_programs.partnership', '4.3', 'Түншлэл ба хөгжил', 'БОНХО'),
                $policy('target_programs.billion_trees', '4.4', 'Тэрбум мод'),
                $policy('target_programs.digital_dornogovi', '4.5', 'Цахим Дорноговь', 'ТЗУХ'),
            ],
        ],
        [
            'key' => 'memorandum',
            'number' => '5',
            'label' => 'Санамж бичиг',
            'reports' => [
                [
                    'key' => 'memorandum.summary',
                    'number' => '5',
                    'label' => 'Улс, ААНБ-тай байгуулсан санамж бичгийн тойм',
                    'template' => 'memorandum_matrix',
                    'columns' => [],
                    'description' => '12 улс, ААНБ-тай 18 төрлийн санамж бичиг — тойм хүснэгт.',
                ],
                [
                    'key' => 'memorandum.register',
                    'number' => '5.x',
                    'label' => 'Хамтын ажиллагааны санамж бичиг — дэлгэрэнгүй',
                    'template' => 'memorandum_register',
                    'columns' => [],
                    'children' => array_map(
                        static fn (array $row) => array_merge($row, ['template' => 'memorandum_register', 'columns' => []]),
                        [
                            ['key' => 'memorandum.register.5_1', 'number' => '5.1', 'label' => 'БНХАУ — Бугат хот'],
                            ['key' => 'memorandum.register.5_2', 'number' => '5.2', 'label' => 'БНХАУ — Ах дүү хотын хэлэлцээр (2026.07)'],
                            ['key' => 'memorandum.register.5_3', 'number' => '5.3', 'label' => 'БНХАУ — Эрээн хот'],
                            ['key' => 'memorandum.register.5_4', 'number' => '5.4', 'label' => 'БНХАУ — Улаанцав хот'],
                            ['key' => 'memorandum.register.5_5', 'number' => '5.5', 'label' => 'БНХАУ — Шилийн гол аймаг'],
                            ['key' => 'memorandum.register.5_6', 'number' => '5.6', 'label' => 'Япон — Шизуока муж'],
                            ['key' => 'memorandum.register.5_7', 'number' => '5.7', 'label' => 'Япон — Шизуока муж, ЖАЙКА'],
                            ['key' => 'memorandum.register.5_8', 'number' => '5.8', 'label' => 'Япон — Бохир усны төсөл (2026.06)'],
                            ['key' => 'memorandum.register.5_9', 'number' => '5.9', 'label' => 'ОХУ — Эрхүү муж'],
                            ['key' => 'memorandum.register.5_10', 'number' => '5.10', 'label' => 'ОХУ — Худалдаа, эдийн засгийн хэлэлцээр (2026.10)'],
                            ['key' => 'memorandum.register.5_11', 'number' => '5.11', 'label' => 'БНСУ — Сайншанд-Ёндун'],
                            ['key' => 'memorandum.register.5_12', 'number' => '5.12', 'label' => 'БНСУ — Улирлын ажилтны солилцоо (2026.02)'],
                            ['key' => 'memorandum.register.5_13', 'number' => '5.13', 'label' => '«Ньюком» ХХК — сэргээгдэх эрчим хүч'],
                            ['key' => 'memorandum.register.5_14', 'number' => '5.14', 'label' => '«Дашваанжил» ХХК — хийн халаалт'],
                            ['key' => 'memorandum.register.5_15', 'number' => '5.15', 'label' => '«Орхон хонь» ХХК — эрчимжсэн хонин'],
                            ['key' => 'memorandum.register.5_16', 'number' => '5.16', 'label' => 'Монголын Хөл Бөмбөгийн Холбоо'],
                            ['key' => 'memorandum.register.5_17', 'number' => '5.17', 'label' => '«Байнари системс» ХХК — Сайншанд сум'],
                            ['key' => 'memorandum.register.5_18', 'number' => '5.18', 'label' => '«Засаг чандмань Майнз» ХХК — Дэлгэрэх сум'],
                            ['key' => 'memorandum.register.5_19', 'number' => '5.19', 'label' => '«Жы Хэ» майнинг — Их говийн хөгжлийн гарц'],
                            ['key' => 'memorandum.register.5_20', 'number' => '5.20', 'label' => '«Мөнх алтан гэгээвч» ХХК — Шинэ сумын төв'],
                        ],
                    ),
                ],
            ],
        ],
        [
            'key' => 'investment',
            'number' => '6',
            'label' => 'Хөрөнгө оруулалтын гэрээ',
            'reports' => [
                [
                    'key' => 'investment.summary',
                    'number' => '6',
                    'label' => 'Хэрэгжүүлэх төсөл — тойм (39 төсөл, 36 ААНБ)',
                    'template' => 'investment_matrix',
                    'columns' => [],
                ],
                [
                    'key' => 'investment.register',
                    'number' => '6.x',
                    'label' => 'Хөрөнгө оруулалтын гэрээ — дэлгэрэнгүй',
                    'template' => 'investment_register',
                    'columns' => [],
                    'children' => array_map(
                        static fn (array $row) => array_merge($row, ['template' => 'investment_register', 'columns' => []]),
                        [
                            ['key' => 'investment.register.airag', 'number' => '6.1', 'label' => 'Айраг сум'],
                            ['key' => 'investment.register.altanshiree', 'number' => '6.2', 'label' => 'Алтанширээ сум'],
                            ['key' => 'investment.register.dalanjargalan', 'number' => '6.3', 'label' => 'Даланжаргалан сум'],
                            ['key' => 'investment.register.delgerekh', 'number' => '6.4', 'label' => 'Дэлгэрэх сум'],
                            ['key' => 'investment.register.ikheht', 'number' => '6.5', 'label' => 'Иххэт сум'],
                            ['key' => 'investment.register.zamyn_uud', 'number' => '6.6', 'label' => 'Замын-Үүд сум'],
                            ['key' => 'investment.register.urgun', 'number' => '6.7', 'label' => 'Өргөн сум'],
                            ['key' => 'investment.register.mandakh', 'number' => '6.8', 'label' => 'Мандах сум'],
                            ['key' => 'investment.register.sainshand_ulaanbadrah', 'number' => '6.9', 'label' => 'Сайншанд, Улаанбадрах сум'],
                            ['key' => 'investment.register.khatanbulag', 'number' => '6.10', 'label' => 'Хатанбулаг сум'],
                            ['key' => 'investment.register.khovsgol', 'number' => '6.11', 'label' => 'Хөвсгөл сум'],
                        ],
                    ),
                ],
            ],
        ],
    ],
];
