<?php

/**
 * Тайлан мэдээлэл — ХШ_ЭЦЭС.docx + албан даалгавар, Excel эх сурвалжууд.
 */

$col = static fn (string $key, string $label, ?string $width = null): array => array_filter([
    'key' => $key,
    'label' => $label,
    'width' => $width,
]);

$policyCols = [
    $col('no', '№', '3rem'),
    $col('document', 'Бодлогын баримт бичиг'),
    $col('count', 'Тоо хэмжээ', '5rem'),
    $col('goal', 'Бодлогын зорилт'),
    $col('measure', 'Арга хэмжээ'),
    $col('clause', 'Заалт', '4rem'),
    $col('progress', 'Явцын хяналт /эхний хагас жил/', '6rem'),
    $col('note', 'Тайлбар'),
];

$officialCols = [
    $col('no', 'Д/Д', '3.5rem'),
    $col('measure', 'Арга хэмжээ'),
    $col('result', 'Биелэлт'),
    $col('percent', 'Биелэлтийн хувь', '5rem'),
];

$lawCols = [
    $col('decision_no', 'Шийдвэрийн д/д', '4rem'),
    $col('clause_no', 'Заалтын д/д', '4rem'),
    $col('decision_title', 'Шийдвэрийн нэр, огноо, дугаар'),
    $col('clause_text', 'Холбогдох заалтын агуулга'),
    $col('action_plan', 'Холбогдох заалтын агуулга /арга хэмжээ/'),
    $col('progress', 'Хэрэгжилтийн явц'),
    $col('evaluation', 'Өөрчлийн үнэлгээ'),
    $col('note', 'ТАЙЛБАР'),
];

$planCols = [
    $col('no', 'д/д', '3rem'),
    $col('activity', 'ЗДҮАХ-т тусгагдсан үйл ажиллагаа'),
    $col('measure', 'Зорилтыг хэрэгжүүлэх арга хэмжээ'),
    $col('period', 'Хэрэгжих хугацаа', '5rem'),
    $col('source', 'Эх үүсвэр', '4rem'),
    $col('budget', 'Нийт хөрөнгийн гүйцэтгэл (сая.төг)', '5rem'),
    $col('indicator', 'Шалгуур үзүүлэлт'),
    $col('unit', 'Хэмжих нэгж', '4rem'),
    $col('baseline', 'Суурь түвшин', '4rem'),
    $col('target', 'Зорилтот түвшин', '5rem'),
    $col('progress', 'Хэрэгжилт (өссөн дүнгээр)', '5rem'),
    $col('percent', 'Хэрэгжилтийн хувь', '4rem'),
    $col('frequency', 'Мэдээлэл цуглуулах давтамж', '4rem'),
    $col('report_to', 'Тайлагнах'),
    $col('department', 'Хэлтэс', '4rem'),
    $col('agency', 'Агентлаг'),
];

$ajhtCols = [
    $col('policy_unit', 'Бодлогын үндслэл'),
    $col('year', 'Жил'),
    $col('clause', 'Заалт'),
    $col('goal', 'Зорилго'),
    $col('measure', 'Арга хэмжээ'),
    $col('indicator', 'Шалгуур үзүүлэлт'),
    $col('unit', 'Хэмжих нэгж'),
    $col('baseline', 'Суурь түвшин'),
    $col('target', 'Зорилтот түвшин'),
    $col('progress', 'Хэрэгжилт'),
    $col('percent', 'Хэрэгжилтийн хувь'),
    $col('department', 'Хариуцах байгууллага'),
];

$itkhCols = [
    $col('decision_no', 'Шийдвэрийн д/д', '4rem'),
    $col('clause_no', 'Заалтын д/д', '4rem'),
    $col('decision_title', 'Шийдвэрийн нэр, огноо, дугаар'),
    $col('clause_text', 'Холбогдох заалтын агуулга'),
    $col('half_year', 'Хагас жилийн тайлан'),
    $col('evaluation', 'Үнэлгээ'),
    $col('note', 'Тайлбар'),
    $col('department', 'Тайлагнах хэлтэс'),
];

$policy = static fn (
    string $key,
    string $number,
    string $label,
    ?string $department = null,
    ?string $description = null,
    ?string $sourceFile = null,
    ?string $template = 'policy_tracking',
    ?string $pdf = null,
): array => array_filter([
    'key' => $key,
    'number' => $number,
    'label' => $label,
    'department' => $department,
    'description' => $description,
    'template' => $template,
    'source_file' => $sourceFile,
    'pdf' => $pdf,
], static fn ($v) => $v !== null);

return [
    'title' => 'Тайлан мэдээлэл',
    'subtitle' => 'Хяналт-шинжилгээ, үнэлгээний хэлтсийн нэгдсэн тайлан, үзүүлэлт',
    'period' => '2026 оны эхний хагас жил',
    'as_of' => '2026.05.10',

    'sources' => [
        ['file' => 'ХШ_үүрэг даалгавар_ЭЦЭС.docx', 'role' => 'Ерөнхий бүтэц, тойм үзүүлэлт'],
        ['file' => '01 АЛБАН ДААЛГАВАР.docx', 'role' => 'Албан даалгавар 01 — үндэсний бичиг'],
        ['file' => '02-АЛБАН ДААЛГАВАР.docx', 'role' => 'Албан даалгавар 02 — Тэрбум мод'],
        ['file' => '03-АЛБАН ДААЛГАВАР.docx', 'role' => 'Албан даалгавар 03 — эрүүл мэнд'],
        ['file' => '04-АЛБАН ДААЛГАВАР.docx', 'role' => 'Албан даалгавар 04 — төсвийн орлого'],
        ['file' => '06-АЛБАН ДААЛГАВАР.docx', 'role' => 'Албан даалгавар 06 — мал аж ахуй'],
        ['file' => '1. Хууль тогтоомж 2026 оны эхний хагас жил.xlsx', 'role' => 'Хууль тогтоомжийн хэрэгжилт (2.5)'],
        ['file' => 'АЖХТ-2026-ЭЦЭС.xlsx', 'role' => 'Аймгийн хөгжлийн жилийн төлөвлөгөө (2.2)'],
        ['file' => 'АЗДҮАХ-2024-2028-2026-оны-хагас-жил-ТАЙЛАН-хуваарилалт.xlsx', 'role' => 'Засаг даргын үйл ажиллагааны хөтөлбөр (2.1)'],
        ['file' => 'ИТХТ-ын хэрэгжилт_2026_.xlsx', 'role' => 'Аймгийн ИТХ-ын тогтоол (2.3)'],
    ],

    'templates' => [
        'policy_tracking' => [
            'label' => 'Бодлогын баримт — хяналтын хүснэгт',
            'columns' => $policyCols,
        ],
        'official_assignment' => [
            'label' => 'Албан даалгавар — арга хэмжээ',
            'columns' => $officialCols,
        ],
        'law_implementation' => [
            'label' => 'Хууль тогтоомж — хэрэгжилт',
            'columns' => $lawCols,
        ],
        'governor_program' => [
            'label' => 'ЗДҮАХ — хагас жилийн тайлан',
            'columns' => $planCols,
        ],
        'annual_plan' => [
            'label' => 'АЖХТ — жилийн төлөвлөгөө',
            'columns' => $ajhtCols,
        ],
        'itkh_decision' => [
            'label' => 'ИТХ-ын тогтоол — хэрэгжилт',
            'columns' => $itkhCols,
        ],
    ],

    'dashboard' => [
        'kpis' => [
            ['key' => 'overall_progress', 'label' => 'Ерөнхий хэрэгжилт (АЖХТ)', 'value' => 46.1, 'unit' => '%', 'hint' => '127 / 204 зорилт'],
            ['key' => 'law_progress', 'label' => 'Хууль тогтоомж', 'value' => 83, 'unit' => '%', 'hint' => '201 арга хэмжээ'],
            ['key' => 'itkh_progress', 'label' => 'ИТХ-ын тогтоол', 'value' => 62.5, 'unit' => '%', 'hint' => '16 шийдвэр'],
            ['key' => 'assignments', 'label' => 'Албан даалгавар', 'value' => 93, 'unit' => 'арга хэмжээ', 'hint' => '5 албан даалгавар'],
        ],
        'sections' => [
            ['key' => 'state_policy', 'label' => 'Төрийн бодлого', 'number' => '1', 'total' => 44, 'progress' => null, 'note' => 'Тогтоол 22, тэмдэглэл 17, захирамж 1, албан даалгавар 4'],
            ['key' => 'local_policy', 'label' => 'Орон нутгийн бодлого', 'number' => '2', 'total' => null, 'progress' => 46.1, 'note' => 'АЖХТ 46,1%'],
            ['key' => 'contracts', 'label' => 'Гэрээ', 'number' => '3', 'total' => null, 'progress' => 56, 'note' => 'Сайдуудтай хамтран ажиллах гэрээ'],
            ['key' => 'target_programs', 'label' => 'Зорилтот хөтөлбөр', 'number' => '4', 'total' => 5, 'progress' => 53.5, 'note' => '4.1–4.2 ~53%; бусад ирүүлээгүй'],
        ],
        'departments' => [
            ['code' => 'tzux', 'label' => 'ТЗУХ', 'progress' => 99, 'tasks' => 18],
            ['code' => 'hezx', 'label' => 'ХЭЗХ', 'progress' => 77.9, 'tasks' => 26],
            ['code' => 'bonxo', 'label' => 'БОНХО', 'progress' => null, 'tasks' => null, 'note' => 'Ирүүлээгүй'],
            ['code' => 'sxzx', 'label' => 'СХЗХ', 'progress' => 75, 'tasks' => 8],
            ['code' => 'stsx', 'label' => 'СТСХ', 'progress' => 76.3, 'tasks' => 44],
            ['code' => 'nbx', 'label' => 'НБХ', 'progress' => 65.6, 'tasks' => 138],
        ],
        'official_assignments' => [
            ['key' => 'local_policy.governor_assignments.script', 'number' => '01', 'label' => 'Үндэсний бичиг', 'department' => 'НБХ', 'measures' => 18, 'progress' => null, 'source' => '01 АЛБАН ДААЛГАВАР.docx'],
            ['key' => 'local_policy.governor_assignments.tree', 'number' => '02', 'label' => 'Тэрбум мод', 'department' => 'БОНХО', 'measures' => 18, 'progress' => null, 'source' => '02-АЛБАН ДААЛГАВАР.docx'],
            ['key' => 'local_policy.governor_assignments.health', 'number' => '03', 'label' => 'Эрүүл мэнд', 'department' => 'НБХ', 'measures' => 23, 'progress' => null, 'source' => '03-АЛБАН ДААЛГАВАР.docx'],
            ['key' => 'local_policy.governor_assignments.budget', 'number' => '04', 'label' => 'Төсвийн орлого', 'department' => 'СТСХ', 'measures' => 18, 'progress' => 38, 'source' => '04-АЛБАН ДААЛГАВАР.docx'],
            ['key' => 'local_policy.governor_assignments.livestock', 'number' => '06', 'label' => 'Мал аж ахуй', 'department' => 'БОНХО', 'measures' => 16, 'progress' => null, 'source' => '06-АЛБАН ДААЛГАВАР.docx'],
        ],
    ],

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
                $policy(
                    'local_policy.governor_program',
                    '2.1',
                    'Аймгийн Засаг даргын үйл ажиллагааны хөтөлбөр',
                    null,
                    'АЗДҮАХ 2024–2028 — 2026 оны хагас жилийн тайлан',
                    'АЗДҮАХ-2024-2028-2026-оны-хагас-жил-ТАЙЛАН-хуваарилалт.xlsx',
                    'governor_program',
                ),
                $policy(
                    'local_policy.annual_plan',
                    '2.2',
                    'Аймгийн хөгжлийн жилийн төлөвлөгөө',
                    null,
                    'АЖХТ-2026 — хэлтэс тус бүрээр',
                    'АЖХТ-2026-ЭЦЭС.xlsx',
                    'annual_plan',
                ),
                $policy(
                    'local_policy.council_decision',
                    '2.3',
                    'Аймгийн ИТХ-ын тогтоол',
                    null,
                    '2025–2026 оны тогтоолын хэрэгжилт',
                    'ИТХТ-ын хэрэгжилт_2026_.xlsx',
                    'itkh_decision',
                ),
                $policy(
                    'local_policy.law_implementation',
                    '2.5',
                    'Хууль тогтоомж, тогтоол шийдвэрийн хэрэгжилт',
                    null,
                    '2026 оны эхний хагас жил — 83% хэрэгжилт',
                    '1. Хууль тогтоомж 2026 оны эхний хагас жил.xlsx',
                    'law_implementation',
                ),
                [
                    'key' => 'local_policy.governor_assignments',
                    'number' => '2.7',
                    'label' => 'Аймгийн Засаг даргын албан даалгаврын хэрэгжилт',
                    'template' => 'official_assignment',
                    'description' => '01, 02, 03, 04, 06 дугаар албан даалгавар',
                    'children' => [
                        array_merge($policy(
                            'local_policy.governor_assignments.script',
                            '2.7.1',
                            '01 — Үндэсний бичгийг хэрэглээ болгох',
                            'НБХ',
                            sourceFile: '01 АЛБАН ДААЛГАВАР.docx',
                            template: 'official_assignment',
                        ), ['measures' => 18]),
                        array_merge($policy(
                            'local_policy.governor_assignments.tree',
                            '2.7.2',
                            '02 — «Тэрбум мод» үндэсний хөдөлгөөн',
                            'БОНХО',
                            sourceFile: '02-АЛБАН ДААЛГАВАР.docx',
                            template: 'official_assignment',
                        ), ['measures' => 18]),
                        array_merge($policy(
                            'local_policy.governor_assignments.health',
                            '2.7.3',
                            '03 — Эрүүл мэндийн салбар',
                            'НБХ',
                            sourceFile: '03-АЛБАН ДААЛГАВАР.docx',
                            template: 'official_assignment',
                        ), ['measures' => 23]),
                        array_merge($policy(
                            'local_policy.governor_assignments.budget',
                            '2.7.4',
                            '04 — Төсвийн орлогын бүрдүүлэлт',
                            'СТСХ',
                            sourceFile: '04-АЛБАН ДААЛГАВАР.docx',
                            template: 'official_assignment',
                        ), ['measures' => 18, 'progress' => 38]),
                        array_merge($policy(
                            'local_policy.governor_assignments.livestock',
                            '2.7.5',
                            '06 — Мал аж ахуйн өвөлжилт, хаваржилт',
                            'БОНХО',
                            sourceFile: '06-АЛБАН ДААЛГАВАР.docx',
                            template: 'official_assignment',
                        ), ['measures' => 16]),
                    ],
                ],
            ],
        ],
        [
            'key' => 'contracts',
            'number' => '3',
            'label' => 'Гэрээ',
            'reports' => [
                $policy('contracts.ministry_gbxn', '3.1', 'ГБХНХЯам — сайдуудтай хамтран ажиллах гэрээ', 'НБХ', pdf: 'contracts.ministry_gbxn.pdf'),
                $policy('contracts.ministry_culture', '3.2', 'Соёлын сайд', pdf: 'contracts.ministry_culture.pdf'),
                $policy('contracts.ministry_health', '3.3', 'Эрүүл мэндийн сайд', pdf: 'contracts.ministry_health.pdf'),
                $policy('contracts.ministry_education', '3.4', 'Боловсролын сайд', pdf: 'contracts.ministry_education.pdf'),
                $policy('contracts.ministry_defense', '3.5', 'Батлан хамгаалахын сайд', 'ЦШ', pdf: 'contracts.ministry_defense.pdf'),
                $policy('contracts.ministry_finance', '3.6', 'Сангийн сайд', 'СТСХ', pdf: 'contracts.ministry_finance.pdf'),
                $policy('contracts.ministry_zgheg', '3.7', 'ЗГХЭГ', 'ТЗУХ', pdf: 'contracts.ministry_zgheg.pdf'),
                $policy('contracts.deputy_gankhuyag', '3.8', 'Шадар сайд Х.Ганхуяг', pdf: 'contracts.deputy_gankhuyag.pdf'),
                $policy('contracts.ministry_digital', '3.9', 'Цахим хөгжлийн сайд', pdf: 'contracts.ministry_digital.pdf'),
                $policy('contracts.ministry_foreign', '3.10', 'Гадаад харилцааны сайд', pdf: 'contracts.ministry_foreign.pdf'),
                $policy('contracts.deputy_dorjhand', '3.11', 'Шадар сайд Т.Доржханд', pdf: 'contracts.deputy_dorjhand.pdf'),
                $policy('contracts.ministry_legal', '3.12', 'Хууль зүйн сайд', 'ХЭЗХ', pdf: 'contracts.ministry_legal.pdf'),
                $policy('contracts.ministry_economy', '3.13', 'Эдийн засаг хөгжлийн сайд', 'БОНХО', pdf: 'contracts.ministry_economy.pdf'),
                $policy('contracts.ministry_environment', '3.14', 'Байгаль орчны сайд', pdf: 'contracts.ministry_environment.pdf'),
                $policy('contracts.ministry_mram', '3.15', 'Аж үйлдвэр, эрдэс баялгийн сайд', pdf: 'contracts.ministry_mram.pdf'),
                $policy('contracts.ministry_urban', '3.16', 'Хот байгуулалтын сайд', pdf: 'contracts.ministry_urban.pdf'),
                $policy('contracts.ministry_food', '3.17', 'Хүнс, хөдөө аж ахуйн сайд', pdf: 'contracts.ministry_food.pdf'),
                $policy('contracts.ministry_energy', '3.18', 'Эрчим хүчний сайд', pdf: 'contracts.ministry_energy.pdf'),
                $policy('contracts.ministry_road', '3.19', 'Зам, тээврийн сайд', pdf: 'contracts.ministry_road.pdf'),
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
                $policy('target_programs.civil_service', '4.2', 'Төрийн захиргааны албан хаагчдын ажиллах нөхцөлийг сайжруулах'),
                $policy('target_programs.partnership', '4.3', 'Түншлэл ба хөгжил', 'БОНХО'),
                $policy('target_programs.billion_trees', '4.4', 'Тэрбум мод'),
                $policy('target_programs.digital_dornogovi', '4.5', 'Цахим Дорноговь', 'ТЗУХ', pdf: 'target_programs.digital_dornogovi.pdf'),
            ],
        ],
    ],
];
