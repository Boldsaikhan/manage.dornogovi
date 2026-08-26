<?php

/**
 * Дотоод модулиудын бүртгэл.
 * key — эрх/маршрут; route — Ziggy нэр; group — хажуугийн цэсийн бүлэг.
 */
return [
    'groups' => [
        'dashboard' => 'Самбар',
        'documents' => 'Бичиг хэрэг',
        'hr' => 'Хүний нөөц',
        'work' => 'Ажлын удирдлага',
        'knowledge' => 'Мэдээлэл, сургалт',
        'systems' => 'Системүүд',
        'admin' => 'Удирдлага',
    ],

    'items' => [
        [
            'key' => 'dept_dashboard',
            'label' => 'Албан хаагчийн самбар',
            'route' => 'dept.dashboard',
            'group' => 'dashboard',
            'icon' => 'chart',
        ],
        [
            'key' => 'tasks',
            'label' => 'Үүрэг даалгавар',
            'route' => 'tasks.index',
            'group' => 'work',
            'icon' => 'clipboard',
        ],
        [
            'key' => 'work_groups',
            'label' => 'Ажлын хэсэг',
            'route' => 'work-groups.index',
            'group' => 'work',
            'icon' => 'users',
        ],
        [
            'key' => 'plans',
            'label' => 'Төлөвлөгөө',
            'route' => 'plans.index',
            'group' => 'work',
            'icon' => 'calendar',
        ],
        [
            'key' => 'meetings',
            'label' => 'Хурлын тэмдэглэл',
            'route' => 'meetings.index',
            'group' => 'work',
            'icon' => 'mic',
        ],
        [
            'key' => 'reports',
            'label' => 'Тайлан мэдээлэл',
            'route' => 'reports.index',
            'group' => 'work',
            'icon' => 'chart',
        ],
        [
            'key' => 'regulations',
            'label' => 'Дотоод журам',
            'route' => 'regulations.index',
            'group' => 'documents',
            'icon' => 'book',
        ],
        [
            'key' => 'decrees',
            'label' => 'Захирамж, тушаал',
            'route' => 'decrees.index',
            'group' => 'documents',
            'icon' => 'file',
        ],
        [
            'key' => 'contracts',
            'label' => 'Гэрээний дугаар',
            'route' => 'contracts.index',
            'group' => 'documents',
            'icon' => 'hash',
        ],
        [
            'key' => 'archives',
            'label' => 'Архив',
            'route' => 'archives.index',
            'group' => 'documents',
            'icon' => 'archive',
        ],
        [
            'key' => 'doc_standards',
            'label' => 'Бичиг хэргийн стандарт',
            'route' => 'doc-standards.index',
            'group' => 'documents',
            'icon' => 'book',
        ],
        [
            'key' => 'leaves',
            'label' => 'Чөлөөний бүртгэл',
            'route' => 'leaves.index',
            'group' => 'hr',
            'icon' => 'calendar',
        ],
        [
            'key' => 'assignments',
            'label' => 'Томилолтын бүртгэл',
            'route' => 'assignments.index',
            'group' => 'hr',
            'icon' => 'plane',
        ],
        [
            'key' => 'phone_directory',
            'label' => 'Утасны жагсаалт',
            'route' => 'phone-directory.index',
            'group' => 'hr',
            'icon' => 'phone',
        ],
        [
            'key' => 'onboarding',
            'label' => 'Гарын авлага, сургалт',
            'route' => 'onboarding.index',
            'group' => 'knowledge',
            'icon' => 'graduation',
        ],
        [
            'key' => 'ai',
            'label' => 'Manage AI',
            'route' => 'ai.index',
            'group' => 'knowledge',
            'icon' => 'sparkles',
        ],
        [
            'key' => 'systems',
            'label' => 'Холбосон системүүд',
            'route' => 'dashboard',
            'group' => 'systems',
            'icon' => 'grid',
        ],
    ],
];
